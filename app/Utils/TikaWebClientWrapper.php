<?php

namespace App\Utils;

use Exception;
use Illuminate\Support\Facades\Config;
use Vaites\ApacheTika\Client as TikaClient;
use Vaites\ApacheTika\Metadata\DocumentMetadata;
use Vaites\ApacheTika\Metadata\ImageMetadata;
use Vaites\ApacheTika\Metadata\Metadata;

class TikaWebClientWrapper
{
    private TikaClient $client;

    /**
     * @throws Exception
     */
    public function buildClient(): void
    {
        if (isset($this->client)) {
            return;
        }
        $this->client = TikaClient::make(Config::get('app.tika_url'));
        $this->client->setTimeout('95');
    }

    /**
     * @param $file
     * @param null $recursive
     *
     * @throws Exception
     *
     * @return DocumentMetadata|ImageMetadata|Metadata
     */
    public function getMetaData($file, $recursive = null)
    {
        $this->buildClient();

        return $this->client->getMetadata($file, $recursive);
    }

    /**
     * @param $file
     * @param null $callback
     * @param bool $append
     *
     * @throws Exception
     */
    public function getHtml($file, $callback = null, $append = true): string
    {
        $this->buildClient();

        return $this->client->getHTML($file, $callback, $append);
    }
}
