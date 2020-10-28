<?php

namespace Tests\Feature\Modules\Card\Integrations\Google\Fakes;

class FakeContent
{
    public string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    public function getBody(): string
    {
        return $this->type;
    }
}
