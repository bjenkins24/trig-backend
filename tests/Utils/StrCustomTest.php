<?php

namespace Tests\Utils;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StrCustomTest extends TestCase
{
    use RefreshDatabase;

    public function testHtmlToMarkdown(): void
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

        $result = Str::htmlToMarkdown($testString, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
        self::assertEquals($expected, $result);

        $result = Str::htmlToMarkdown('', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']);
        self::assertEquals('', $result);
    }

    public function testHasExtension(): void
    {
        $withExtension = Str::hasExtension('Microsoft Word - Good_Group_Product_Manager.doc');
        self::assertTrue($withExtension);

        $withExtension = Str::hasExtension('Microsoft Word - Good_Group_Product_Manager');
        self::assertFalse($withExtension);
    }

    public function testToSingleSpace(): void
    {
        $result = Str::toSingleSpace('Microsoft Word - Good  Group   Product    Manager.doc');
        self::assertEquals('Microsoft Word - Good Group Product Manager.doc', $result);
    }
}
