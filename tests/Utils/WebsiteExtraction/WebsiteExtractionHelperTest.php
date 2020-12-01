<?php

namespace Tests\Utils\WebsiteExtraction;

use App\Utils\WebsiteExtraction\WebsiteExtractionHelper;
use Tests\TestCase;

class WebsiteExtractionHelperTest extends TestCase
{
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

        $result = app(WebsiteExtractionHelper::class)->makeContentSearchable($testString);
        self::assertEquals($expected, $result);

        $result = app(WebsiteExtractionHelper::class)->makeContentSearchable('');
        self::assertEquals('', $result);
    }

    /**
     * @group n
     */
    public function testSimpleFetch()
    {
        $result = app(WebsiteExtractionHelper::class)->fullFetch('https://laracasts.com/discuss/channels/eloquent/delete-record-using-whereasdasd');
        dd($result);
    }
}
