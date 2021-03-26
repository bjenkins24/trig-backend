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

    public function getCardInitialData(Card $card): Collection
    {
        try {
            $website = $this->websiteExtractionHelper->simpleFetch($card->url)->parseContent();
        } catch (WebsiteNotFound $exception) {
            $this->cardSyncRepository->create([
                'card_id' => $card->id,
                'status'  => 2,
            ]);

            return collect([]);
        } catch (Exception $exception) {
            Log::notice('Failed initial attempt at extracting a link for '.$card->url.': '.$exception->getMessage());

            return collect([]);
        }

        return $this->getContentFromWebsite($card, $website);
    }

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
            // Todo: A 404 for a card that doesn't have content yet should just delete the card (but we have to alert the
            // user not sure how we should do that yet)
            $this->cardSyncRepository->create([
                'card_id' => $card->id,
                'status'  => 2,
            ]);

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
