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

    public function testMakeContentSearchable(): void
    {
        $testString = <<<EOD
<h1>Heading 1</h1>
<h2>Heading 2</h2>
<h3>Heading 3</h3>
<h4>Heading 4</h4>
<h5>Heading 5</h5>
<h6>Heading 6</h6>
<p>Paragraph Text</p><script>alert('xss');</script>
<pre>\$mycool++;</pre>
<p><img alt='friend' src='bad_image.jpg' /><a href='coolplace.html'>My cool link</a></p>
<p><strong>I'm super bold and cool</strong></p>

<ul><li>First Item</li><li>Second Item</li></ul>
<blockquote>Hello</blockquote>
EOD;
        $expected = <<<EOD
Paragraph Text

```
\$mycool++;
```

My cool link

**I'm super bold and cool**

- First Item
- Second Item

> Hello
EOD;

        $result = app(WebsiteContentHelper::class)->makeContentSearchable($testString);
        self::assertEquals($expected, $result);
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
