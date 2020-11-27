<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Utils\WebsiteContentHelper;
use Exception;
use Illuminate\Support\Collection;

class LinkContent implements ContentInterface
{
    private WebsiteContentHelper $websiteContentHelper;

    public function __construct(WebsiteContentHelper $websiteContentHelper)
    {
        $this->websiteContentHelper = $websiteContentHelper;
    }

    /**
     * @throws Exception
     */
    public function getCardContentData(Card $card, ?string $id = null, ?string $mimeType = null): Collection
    {
        $website = $this->websiteContentHelper->getWebsite($card->url);

        return collect([
            'title'        => $website->get('title'),
            'content'      => $website->get('html'),
            'author'       => $website->get('author'),
            'description'  => $website->get('excerpt'),
            'image'        => $website->get('image'),
        ]);
    }
}
