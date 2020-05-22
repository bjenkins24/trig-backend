<?php

namespace App\Utils\TikaWebClient;

use Vaites\ApacheTika\Client as TikaClient;

class TikaWebClientWrapper implements TikaWebClientInterface
{
    private TikaClient $client;

    public function __construct()
    {
        $this->client = TikaClient::make(\Config::get('app.tika_url'));
    }

    public function getMetaData($file, $recursive = null)
    {
        $this->client->getMetadata($file, $recursive);
    }

    public function getText($file, $callback = null, $append = true)
    {
        $this->client->getText($file, $callback, $append);
    }
}
