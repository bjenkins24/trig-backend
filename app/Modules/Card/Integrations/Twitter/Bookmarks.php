<?php

namespace App\Modules\Card\Integrations\Twitter;

use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\CardType\CardTypeRepository;
use Exception;
use Illuminate\Support\Collection;
use simplehtmldom\HtmlDocument;

class Bookmarks
{
    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;

    public function __construct(CardRepository $cardRepository, CardTypeRepository $cardTypeRepository)
    {
        $this->cardRepository = $cardRepository;
        $this->cardTypeRepository = $cardTypeRepository;
    }

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
            $contentContainer = $tweet->find('[lang]')[0] ?? null;
            if ($contentContainer) {
                $content = $contentContainer->children()[0]->innertext();
            } else {
                $content = '';
            }
            $avatar = $tweet->find('img')[0]->getAttribute('src');
            $images = collect($tweet->find('[alt="Image"]'))->reduce(static function ($carry, $image) {
                $carry->push($image->getAttribute('src'));

                return $carry;
            }, collect([]));

            $reply = collect([]);
            if ($contentContainer) {
                $replyContainer = $contentContainer->parent()->next_sibling()->find('div[role="link"]');
                if ($replyContainer[0] ?? null) {
                    $nameContainer = $replyContainer[0]->find('[role="presentation"]')[0] ?? null;
                    if ($nameContainer) {
                        $nameContainerSpan = $nameContainer->next_sibling()->find('span')[1] ?? null;
                        if ($nameContainerSpan) {
                            $reply->put('name', $nameContainerSpan->innertext());
                        }
                        $handleContainerSpan = $nameContainer->parent()->next_sibling()->find('span')[0] ?? null;
                        if ($handleContainerSpan) {
                            $reply->put('handle', $handleContainerSpan->innertext());
                        }
                    }
                    $timeCreated = $replyContainer[0]->find('time')[0] ?? null;
                    if ($timeCreated) {
                        $reply->put('created_at', $timeCreated->getAttribute('datetime'));
                    }
                    $replyAvatar = $replyContainer[0]->find('[role="presentation"] img')[0] ?? null;
                    if ($replyAvatar) {
                        $reply->put('avatar', $replyAvatar->getAttribute('src'));
                    }
                    $replyContent = $replyContainer[0]->firstChild()->children()[1]->children();
                    if ($replyContent[1] ?? null) {
                        $reply->put('replying_to', $replyContent[0]->innertext());
                        $reply->put('content', $this->getContentString($replyContent[1]->children));
                    } else {
                        $reply->put('content', $this->getContentString($replyContent[0]->children));
                        $image = $replyContainer[0]->find('[data-testid="tweetPhoto"] img')[0] ?? null;
                        if ($image) {
                            $reply->put('image', $image->getAttribute('src'));
                        }
                    }
                }
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
                    'name'       => $name,
                    'url'        => $url,
                    'handle'     => $handle,
                    'created_at' => $created,
                    'avatar'     => $avatar,
                    'content'    => $content ?? $this->getContentString($content),
                    'images'     => $images,
                    'reply'      => $reply,
                    'link'       => $link,
                ]));

            return $carry;
        }, collect([]));
    }

    public function getTweetsFromArrayOfHtml(array $raw): Collection
    {
        $tweets = collect([]);
        foreach ($raw as $html) {
            $tweets = $tweets->merge($this->getTweets($html));
        }

        return $tweets;
    }

    public function saveTweetsFromArrayOfHtml(array $raw, User $user): void
    {
        $tweets = $this->getTweetsFromArrayOfHtml($raw);
        $tweets->each(function ($tweet) use ($user) {
            $fields = [
                'card_type_id'      => $this->cardTypeRepository->firstOrCreate('tweet')->id,
                'user_id'           => $user->id,
                'url'               => $tweet->get('url'),
                'actual_created_at' => $tweet->get('created_at'),
                'content'           => $tweet->get('content'),
                'title'             => $tweet->get('content'),
                'tweet'             => [
                   'name'    => $tweet->get('name'),
                   'handle'  => $tweet->get('handle'),
                   'avatar'  => $tweet->get('avatar'),
                ],
            ];
            if ($tweet->get('link')) {
                $fields['tweet']['link'] = $tweet->get('link');
            }
            if ($tweet->get('reply')) {
                $fields['tweet']['reply'] = $tweet->get('reply');
            }
            try {
                $this->cardRepository->upsert($fields);
            } catch (Exception $exception) {
                dd($exception->getMessage());
            }
        });
    }
}
