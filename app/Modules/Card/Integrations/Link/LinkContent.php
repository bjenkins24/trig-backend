<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Modules\CardSync\CardSyncRepository;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use App\Utils\WebsiteExtraction\Website;
use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class LinkContent implements ContentInterface
{
    public const TOTAL_ATTEMPTS = 4;
    private WebsiteExtractionFactory $websiteExtractionFactory;
    private WebsiteExtractionHelper $websiteExtractionHelper;
    private int $attempts = 0;

    public function __construct(
        WebsiteExtractionFactory $websiteExtractionFactory,
        WebsiteExtractionHelper $websiteExtractionHelper,
        CardSyncRepository $cardSyncRepository
    ) {
        $this->websiteExtractionFactory = $websiteExtractionFactory;
        $this->websiteExtractionHelper = $websiteExtractionHelper;
        $this->cardSyncRepository = $cardSyncRepository;
    }

    /**
     * This should really be it's own class, but I'm lazy right now.
     */
    private function adjustBySite(Card $card, Website $website): Website
    {
        if (false !== strpos($card->url, 'twitter.com')) {
            $website->setTitle($website->getTitle().': '.$website->getExcerpt());
        }

        return $website;
    }

    private function getContentFromWebsite(Card $card, ?Website $website): Collection
    {
        if (! $website || ! $website->getRawContent()) {
            return collect([]);
        }

        $website = $this->adjustBySite($card, $website);

        return collect([
            'title'        => $website->getTitle(),
            'content'      => $website->getContent(),
            'author'       => $website->getAuthor(),
            'description'  => $website->getExcerpt(),
            'image'        => $website->getImage(),
            'screenshot'   => $website->getScreenshot(),
        ]);
    }

    /**
     * @throws Exception
     */
    public function getCardInitialData(Card $card): Collection
    {
        try {
            $website = $this->websiteExtractionHelper->simpleFetch($card->url)->parseContent();
        } catch (WebsiteNotFound $exception) {
            // This was my original solution to deleting a card that 404'd. But there's no point because
            // we're going to actually delete it below which would delete this row. In the future we'll likely
            // need to alert the user that we deleted the card because it 404'd, but for now we're just going
            // to delete it and see what happens because there's no way to set up notifications on that admin right now
//            $this->cardSyncRepository->create([
//                'card_id' => $card->id,
//                'status'  => 2,
//            ]);
            $card->delete();

            return collect([]);
        } catch (Exception $exception) {
            Log::notice('Failed initial attempt at extracting a link for '.$card->url.': '.$exception->getMessage());

            return collect([]);
        }

        return $this->getContentFromWebsite($card, $website);
    }

    /**
     * @throws Exception
     */
    public function getCardContentData(Card $card, ?string $id = null, ?string $mimeType = null, int $currentRetryAttempt = 0): Collection
    {
        ini_set('max_execution_time', 120);
        $websiteExtraction = $this->websiteExtractionFactory->make($card->url);
        if (! $websiteExtraction) {
            return collect([]);
        }
        try {
            $website = $websiteExtraction->getWebsite($currentRetryAttempt);
        } catch (WebsiteNotFound $exception) {
            $card->delete();

            return collect([]);
        } catch (Exception $exception) {
            Log::notice('Failed website extraction on attempt '.$this->attempts.' for '.$card->url.': '.$exception->getMessage());
            ++$this->attempts;
            // Enough retrying IT FAILED!
            if ($this->attempts >= self::TOTAL_ATTEMPTS) {
                $this->cardSyncRepository->create([
                    'card_id' => $card->id,
                    // Failed sync
                    'status' => 0,
                ]);

                return collect([]);
            }

            return $this->getCardContentData($card, $id, $mimeType, $this->attempts);
        }

        return $this->getContentFromWebsite($card, $website);
    }
}
