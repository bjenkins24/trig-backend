<?php

namespace App\Modules\Card\Integrations\Twitter;

use Illuminate\Support\Collection;
use simplehtmldom\HtmlDocument;

class Bookmarks
{
    private function getContentString(array $content): string
    {
        return collect($content)->reduce(function ($carry, $node) {
            if ('span' === $node->tag) {
                $carry .= $node->innertext();
            }
            // Emoji's are images with an alt being non-twitter emoji's
            if ('img' === $node->tag) {
                $carry .= '<img src="'.$node->getAttribute('src').'" alt="'.$node->getAttribute('alt').'" />';
            }
            // Mentions start with a div
            if ('div' === $node->tag) {
                $carry .= $node->firstChild()->firstChild()->innertext();
            }

            return $carry;
        }, '');
    }

    public function getTweets(string $rawHtml): Collection
    {
        $html = (new HtmlDocument())->load($rawHtml);
        $rawTweets = collect($html->find('[data-testid="tweet"]'));

        return $rawTweets->reduce(function ($carry, $tweet) {
            $names = $tweet->find('a [dir]');
            $url = 'https://twitter.com'.$tweet->find('a')[2]->getAttribute('href');
            $name = $names[0]->firstChild()->firstChild()->innertext();
            $handle = $names[2]->firstChild()->innertext();
            $created = $tweet->find('time')[0]->getAttribute('datetime');
            $content = $tweet->find('[lang]')[0]->children();
            $avatar = $tweet->find('img')[0]->getAttribute('src');
            $images = collect($tweet->find('[alt="Image"]'))->reduce(static function ($carry, $image) {
                $carry->push($image->getAttribute('src'));

                return $carry;
            }, collect([]));

            $replyContainer = $tweet->find('[lang]')[0]->parent()->next_sibling()->find('div[role="link"]');
            $reply = collect([]);
            if ($replyContainer) {
                $reply->put('name', $replyContainer[0]->find('[role="presentation"]')[0]->next_sibling()->find('span')[1]->innertext());
                $reply->put('handle', $replyContainer[0]->find('[role="presentation"]')[0]->parent()->next_sibling()->find('span')[0]->innertext());
                $reply->put('created', $replyContainer[0]->find('time')[0]->getAttribute('datetime'));
                $reply->put('avatar', $replyContainer[0]->find('[role="presentation"] img')[0]->getAttribute('src'));
                $replyContent = $replyContainer[0]->firstChild()->children()[1]->children();
                $reply->put('replying_to', $replyContent[0]->innertext());
                $reply->put('content', $this->getContentString($replyContent[1]->children));
            }

            $linkContainer = $tweet->find('[data-testid="card.wrapper"]');
            $link = collect([]);
            if ($linkContainer) {
                $link->put('href', $linkContainer[0]->find('a')[0]->getAttribute('href'));
                $link->put('image_src', $linkContainer[0]->find('img')[0]->getAttribute('src'));
                $linkContent = $linkContainer[0]->find('[data-testid="card.layoutLarge.detail"]')[0]->children();
                $link->put('url', $linkContent[0]->firstChild()->firstChild()->innertext());
                $link->put('title', $linkContent[1]->firstChild()->firstChild()->innertext());
                $link->put('description', $linkContent[2]->firstChild()->firstChild()->innertext());
            }

            // Key the tweets by handle and created time, so we don't get duplicate tweets
            $carry->put($handle.$created, collect([
                'name'     => $name,
                'url'      => $url,
                'handle'   => $handle,
                'created'  => $created,
                'avatar'   => $avatar,
                'content'  => $this->getContentString($content),
                'images'   => $images,
                'reply'    => $reply,
                'link'     => $link,
       ]));

            return $carry;
        }, collect([]));
    }
}
