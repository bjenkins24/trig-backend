<?php

namespace Tests\Utils;

use App\Utils\WebsiteContentHelper;
use Tests\TestCase;

class WebsiteContentHelperTest extends TestCase
{
    /**
     * @dataProvider adjustUrlProvider
     */
    public function testAdjustUrl(string $givenUrl, string $expectedUrl): void
    {
        $newLink = app(WebsiteContentHelper::class)->adjustUrl($givenUrl);
        self::assertEquals(
            $expectedUrl,
            $newLink
        );
    }

    public function adjustUrlProvider(): array
    {
        return [
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit?usp=sharing',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
            [
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/edit',
                'https://docs.google.com/document/d/1UQ8oR8EqHrOB9DCmbPIfopVeSP-I18Ot4nTDW_VSlPs/export/html',
            ],
        ];
    }
}
