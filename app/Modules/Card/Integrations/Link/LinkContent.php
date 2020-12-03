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
    public const TOTAL_ATTEMPTS = 3;
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
        ini_set('max_execution_time', 120);
        $websiteExtraction = $this->websiteExtractionFactory->make($card->url);
        if (! $websiteExtraction) {
            return collect([]);
        }
        try {
            $website = $websiteExtraction->getWebsite($currentRetryAttempt);
        } catch (WebsiteNotFound $exception) {
            $this->cardSyncRepository->create([
                'card_id' => $card->id,
                'status'  => 2,
            ]);

            return collect([]);
        } catch (Exception $exception) {
            ++$this->attempts;
            // Enough retrying it FAILED!
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

        return collect([
            'title'        => $website->get('title'),
            'content'      => $website->get('html'),
            'author'       => $website->get('author'),
            'description'  => $website->get('excerpt'),
            'image'        => $website->get('image'),
        ]);
    }
}
