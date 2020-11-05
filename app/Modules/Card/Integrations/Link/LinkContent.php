<?php

namespace App\Modules\Card\Integrations\Link;

use App\Models\Card;
use App\Modules\Card\Interfaces\ContentInterface;
use App\Utils\ExtractDataHelper;
use Illuminate\Support\Collection;

class LinkContent implements ContentInterface
{
    private ExtractDataHelper $extractDataHelper;

    public function __construct(ExtractDataHelper $extractDataHelper)
    {
        $this->extractDataHelper = $extractDataHelper;
    }

    public function getCardContentData(Card $card, ?string $id = null, ?string $mimeType = null): Collection
    {
        $website = $this->extractDataHelper->getWebsite($card->url);

        return collect([
            'title'        => $website->get('title'),
            'content'      => $website->get('text'),
            'author'       => $website->get('author'),
            'description'  => $website->get('excerpt'),
            'image'        => $website->get('image'),
        ]);
    }
}
