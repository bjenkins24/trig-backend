<?php

namespace Tests\Utils;

use App\Utils\StrCustom;
use Tests\TestCase;

class StrCustomTest extends TestCase
{
    public function testPurifyHtml()
    {
        $result = StrCustom::purifyHtml('<h1>hello</h1><script>sup</script><a href="http://google.com">Google</a><p>Goodbye</p>');
        dd($result);
    }
}
