<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Modules\CardSync\CardSyncRepository;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use App\Utils\WebsiteExtraction\WebsiteExtractionFactory;
use Exception;
use Illuminate\Support\Collection;

class LinkContent implements ContentInterface
{
    public const TOTAL_ATTEMPTS = 4;
    private WebsiteExtractionFactory $websiteExtractionFactory;
    private int $attempts = 0;

    public function __construct(
        WebsiteExtractionFactory $websiteExtractionFactory,
        CardSyncRepository $cardSyncRepository
    ) {
        $this->websiteExtractionFactory = $websiteExtractionFactory;
        $this->cardSyncRepository = $cardSyncRepository;
    }

    public function getCardContentData(Card $card, ?string $id = null, ?string $mimeType = null, int $currentRetryAttempt = 0): Collection
    {
        \Log::notice('5. Get card content start '.json_encode($card));
        ini_set('max_execution_time', 120);
        $websiteExtraction = $this->websiteExtractionFactory->make($card->url);
        if (! $websiteExtraction) {
            \Log::notice('5.5 No website extraction class');

            return collect([]);
        }
        try {
            $website = $websiteExtraction->getWebsite($currentRetryAttempt);
        } catch (WebsiteNotFound $exception) {
            $this->cardSyncRepository->create([
                'card_id' => $card->id,
                'status'  => 2,
            ]);
            \Log::notice('5.75 404 website');

            return collect([]);
        } catch (Exception $exception) {
            \Log::notice('5.8 Website extraction failed: attempt '.$this->attempts);
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

        \Log::notice('10.5 got website: '.json_encode($website));

        if (! $website || ! $website->getRawContent()) {
            \Log::notice('5.9 Website no content or website');

            return collect([]);
        }

        return collect([
            'title'        => $website->getTitle(),
            'content'      => $website->getContent(),
            'author'       => $website->getAuthor(),
            'description'  => $website->getExcerpt(),
            'image'        => $website->getImage() ?? $website->getScreenshot(),
        ]);
    }
}
