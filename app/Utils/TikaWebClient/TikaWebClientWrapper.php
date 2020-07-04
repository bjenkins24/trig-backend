<?php

namespace App\Utils\TikaWebClient;

use Exception;
use Illuminate\Support\Facades\Config;
use Vaites\ApacheTika\Client as TikaClient;
use Vaites\ApacheTika\Metadata\DocumentMetadata;
use Vaites\ApacheTika\Metadata\ImageMetadata;
use Vaites\ApacheTika\Metadata\Metadata;

class TikaWebClientWrapper implements TikaWebClientInterface
{
    private TikaClient $client;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->client = TikaClient::make(Config::get('app.tika_url'));
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
        return $this->client->getMetadata($file, $recursive);
    }

    /**
     * @param $file
     * @param null $callback
     * @param bool $append
     *
     * @throws Exception
     */
    public function getText($file, $callback = null, $append = true): string
    {
        return $this->client->getText($file, $callback, $append);
    }
}
