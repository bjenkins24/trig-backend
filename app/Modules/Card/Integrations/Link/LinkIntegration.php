<?php

namespace App\Modules\Card\Integrations\Link;

use andreskrey\Readability\ParseException;
use App\Models\User;
use App\Models\Workspace;
use App\Modules\Card\Interfaces\IntegrationInterface;
use App\Utils\WebsiteExtraction\Exceptions\WebsiteNotFound;
use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use App\Utils\WebsiteExtraction\WebsiteFactory;
use Exception;

class LinkIntegration implements IntegrationInterface
{
    protected WebsiteExtractionHelper $websiteExtractionHelper;
    protected WebsiteFactory $websiteFactory;

    public function __construct(WebsiteExtractionHelper $websiteExtractionHelper, WebsiteFactory $websiteFactory)
    {
        $this->websiteExtractionHelper = $websiteExtractionHelper;
        $this->websiteFactory = $websiteFactory;
    }

    public function checkAuthed(string $url, string $rawHtml): bool
    {
        $isAuthed = false;
        try {
            $fetchedWebsite = $this->websiteExtractionHelper->simpleFetch($url)->parseContent();
        } catch (WebsiteNotFound | ParseException | Exception $exception) {
            // If you get a 404 that's pretty odd - the user is literally sending it FROM that url
            // So we're going to just say if curl 404's you ARE likely authed. Same thing can be said if
            // readability cannot parse the text - it's likely an authed page
            $isAuthed = true;
        }

        if (! $isAuthed && isset($fetchedWebsite)) {
            $rawHtmlWebsite = $this->websiteFactory->make($rawHtml)->parseContent();
            $percentSimilarContent = 0;
            $percentSimilarTitle = 0;
            similar_text($rawHtmlWebsite->getContent(), $fetchedWebsite->getContent(), $percentSimilarContent);
            similar_text($rawHtmlWebsite->getTitle(), $fetchedWebsite->getTitle(), $percentSimilarTitle);
            $contentThreshold = 80;
            // If the titles are almost identical that's a good indication that we're not logged in
            // so we can lower our content threshold of similarity
            if ($percentSimilarTitle > 90) {
                $contentThreshold = 65;
            }
            if ($percentSimilarContent < $contentThreshold) {
                $isAuthed = true;
            }
        }

        return $isAuthed;
    }

    public static function getIntegrationKey(): string
    {
        return 'link';
    }

    public function getAllCardData(User $user, Workspace $workspace, ?int $since): array
    {
        return [];
    }
}
