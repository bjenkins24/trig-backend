<?php

namespace App\Utils\TikaWebClient;

interface TikaWebClientInterface
{
    public function getMetadata($file, $recursive = null);

    public function getText($file, $callback = null, $append = true);
}
