<?php

namespace App\Utils;

use andreskrey\Readability\Configuration as ReadabilityConfiguration;
use andreskrey\Readability\ParseException as ReadabilityParseException;
use andreskrey\Readability\Readability;
use Html2Text\Html2Text;
use Vaites\ApacheTika\Client as TikaClient;

class ExtractDataHelper
{
    private $client;

    public function __construct()
    {
        $this->client = TikaClient::make(\Config::get('app.tika_url'));
    }

    public function __call(string $name, array $arguments)
    {
        return call_user_func_array([$this->client, $name], $arguments);
    }

    public function getWebsite(string $url)
    {
        $readability = new Readability(new ReadabilityConfiguration());
        $html = file_get_contents($url);

        try {
            $readability->parse($html);

            return (new Html2Text($readability))->getText();
        } catch (ReadabilityParseException $e) {
            echo sprintf('Error processing text: %s', $e->getMessage());
        }
    }
}
