<?php

namespace App\Modules\Card\Integrations\Google;

class NoConstructor
{
    private string $foo;

    public function __construct()
    {
        $this->foo = 'bar';
    }

    public function getFoo(): string
    {
        $this->doNothing();
        return $this->foo;
    }

    public function doNothing(): string
    {
        return '';
    }
}
