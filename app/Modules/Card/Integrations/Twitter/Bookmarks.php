<?php

namespace App\Modules\Card\Integrations\Twitter;

use App\Jobs\GetTags;
use App\Jobs\TwitterBookmarks;
use App\Models\User;
use App\Modules\Card\CardRepository;
use App\Modules\CardTag\CardTagRepository;
use App\Modules\CardType\CardTypeRepository;
use Exception;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use simplehtmldom\HtmlDocument;

class Bookmarks
{
    private CardRepository $cardRepository;
    private CardTypeRepository $cardTypeRepository;
    private CardTagRepository $cardTagRepository;

    public function __construct(
        CardRepository $cardRepository,
        CardTypeRepository $cardTypeRepository,
        CardTagRepository $cardTagRepository
    ) {
        $this->cardRepository = $cardRepository;
        $this->cardTypeRepository = $cardTypeRepository;
        $this->cardTagRepository = $cardTagRepository;
    }

    // https://stackoverflow.com/questions/17646041/php-how-to-keep-line-breaks-using-nl2br-with-html-purifier#answer-18065106
    private function nl2brPurify($string)
    {
        // Step 1: Add <br /> tags for each line-break
        $string = nl2br($string);

        // Step 2: Remove the actual line-breaks
        $string = str_replace(["\n", "\r"], '', $string);

        // Step 3: Restore the line-breaks that are inside <pre></pre> tags
        if (preg_match_all('/<pre>(.*?)<\/pre>/', $string, $match)) {
            foreach ($match as $a) {
                foreach ($a as $b) {
                    $string = str_replace('<pre>'.$b.'</pre>', '<pre>'.str_replace('<br />', PHP_EOL, $b).'</pre>', $string);
                }
            }
        }

        // Step 4: Removes extra <br /> tags

        // Before <pre> tags
        $string = str_replace('<br /><br /><br /><pre>', '<br /><br /><pre>', $string);
        // After </pre> tags
        $string = str_replace('</pre><br /><br />', '</pre><br />', $string);

        // Arround <ul></ul> tags
        $string = str_replace('<br /><br /><ul>', '<br /><ul>', $string);
        $string = str_replace('</ul><br /><br />', '</ul><br />', $string);
        // Inside <ul> </ul> tags
        $string = str_replace('<ul><br />', '<ul>', $string);
        $string = str_replace('<br /></ul>', '</ul>', $string);

        // Arround <ol></ol> tags
        $string = str_replace('<br /><br /><ol>', '<br /><ol>', $string);
        $string = str_replace('</ol><br /><br />', '</ol><br />', $string);
        // Inside <ol> </ol> tags
        $string = str_replace('<ol><br />', '<ol>', $string);
        $string = str_replace('<br /></ol>', '</ol>', $string);

        // Arround <li></li> tags
        $string = str_replace('<br /><li>', '<li>', $string);
        $string = str_replace('</li><br />', '</li>', $string);

        return $string;
    }

    private function purify(string $html): string
    {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.AllowedAttributes', 'img.src,img.title,img.alt');
        $htmlPurifier = new HTMLPurifier($config);

        $purifiedHtml = $htmlPurifier->purify($html);

        $htmlDom = (new HtmlDocument())->load($purifiedHtml, true, false);
        $topLevelSpans = collect($htmlDom->find('span'));

        // Add <br>'s
        $spans = $topLevelSpans->map(function ($span) {
            $content = $span->innertext();
            $content = $this->nl2brPurify($content);
            // SimpleHtmlDom adds one \n but then a bunch of spaces. This is a fix for that
            return preg_replace('/( )\1{23,}/', '<br />', $content);
        });

        $allText = str_replace(["\r", "\n"], '', (string) $htmlDom);
        $allText = ltrim(trim($allText));
        $allText = preg_replace('/( )\1{23,}/', '', $allText);
        $finalDom = (new HtmlDocument())->load($allText);

        $spans->each(function ($span, $count) use ($finalDom) {
            $finalDom->find('span')[$count]->outertext = '<span>'.$span.'</span>';
        });

        $finalString = (string) $finalDom;

        return str_replace(['<a>', '</a>'], ['<span class="card__link">', '</span>'], $finalString);
    }

    public function getTweets(string $rawHtml): Collection
    {
        $html = (new HtmlDocument())->load($rawHtml, true, false);
        $rawTweets = collect($html->find('[data-testid="tweet"]'));

        return $rawTweets->reduce(function ($carry, $tweet) {
            $names = $tweet->find('a [dir]');
            $url = '';
            if ($tweet->find('a')[2] ?? null) {
                $url = 'https://twitter.com'.$tweet->find('a')[2]->getAttribute('href');
            } else {
                Log::error("Url couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
            }
            $name = '';
            if ($names[0] ?? null) {
                $name = $names[0]->firstChild()->firstChild()->innertext();
            } else {
                Log::error("Name couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
            }
            $handle = '';
            if ($names[2] ?? null) {
                $handle = $names[2]->firstChild()->innertext();
            } else {
                Log::error("Handle couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
            }
            $created = '';
            if ($tweet->find('time')[0] ?? null) {
                $created = $tweet->find('time')[0]->getAttribute('datetime');
            } else {
                Log::error("Time couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
            }
            $contentContainer = $tweet->find('[lang]')[0] ?? null;
            if ($contentContainer) {
                $content = $this->purify($contentContainer->innertext());
            } else {
                $content = '';
                Log::error("Content couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
            }
            $avatar = '';
            if ($tweet->find('img')[0] ?? null) {
                $avatar = $tweet->find('img')[0]->getAttribute('src');
            } else {
                Log::error("Avatar couldn't be fetched for tweet", [$this->purify($tweet->innertext())]);
            }
            $images = collect($tweet->find('[data-testid="tweetPhoto"] img'))->reduce(static function ($carry, $image) {
                $carry->push($image->getAttribute('src'));

                return $carry;
            }, collect([]));

            $reply = collect([]);
            if ($contentContainer) {
                $replyContainer = $contentContainer->parent()->next_sibling()->find('div[role="link"]');
                if ($replyContainer[0] ?? null) {
                    $nameContainer = $replyContainer[0]->find('[role="presentation"]')[0] ?? null;
                    if ($nameContainer) {
                        $nameContainerSibling = $nameContainer->next_sibling();
                        if ($nameContainerSibling) {
                            $nameContainerSpan = $nameContainerSibling->find('span')[1] ?? null;
                            if ($nameContainerSpan) {
                                $reply->put('name', $nameContainerSpan->innertext());
                            } else {
                                Log::error("Name couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
                            }
                        } else {
                            Log::error("Name couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
                        }

                        $handleContainerSibling = $nameContainer->parent()->next_sibling();
                        if ($handleContainerSibling) {
                            $handleContainerSpan = $nameContainerSibling->find('span')[0] ?? null;
                            if ($handleContainerSpan) {
                                $reply->put('handle', $handleContainerSpan->innertext());
                            } else {
                                Log::error("Handle couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
                            }
                        } else {
                            Log::error("Handle couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
                        }
                    } else {
                        Log::error("Name and handle couldn't be fetched for tweet.", [$this->purify($tweet->innertext())]);
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
                        if (false !== strpos($replyContent[1], 'Show this thread')) {
                            $reply->put('content', $this->purify($replyContent[0]));
                        } else {
                            $reply->put('replying_to', $this->purify($replyContent[0]->innertext()));
                            $reply->put('content', $this->purify($replyContent[1]));
                        }
                    } else {
                        $reply->put('content', $this->purify($replyContent[0]));
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
                $linkImage = $linkContainer[0]->find('img')[0] ?? null;
                if ($linkImage) {
                    $link->put('image_src', $linkImage->getAttribute('src'));
                }
                $linkContent = $linkContainer[0]->find('[data-testid="card.layoutLarge.detail"]')[0] ?? null;
                if ($linkContent) {
                    $linkContent = $linkContent->children();
                    $link->put('url', $linkContent[0]->firstChild()->firstChild()->innertext());
                    $link->put('title', $linkContent[1]->firstChild()->firstChild()->innertext());
                    $link->put('description', $this->purify($linkContent[2]->firstChild()->firstChild()->innertext()));
                }
            }

            // Key the tweets by handle and created time, so we don't get duplicate tweets
            $carry->put($handle.$created, collect([
                    'name'       => $name,
                    'url'        => $url,
                    'handle'     => $handle,
                    'created_at' => $created,
                    'avatar'     => $avatar,
                    'content'    => $content,
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
                   'images'  => $tweet->get('images'),
                ],
            ];
            if ($tweet->get('link')) {
                $fields['tweet']['link'] = $tweet->get('link');
            }
            if ($tweet->get('reply')) {
                $fields['tweet']['reply'] = $tweet->get('reply');
            }
            try {
                $card = $this->cardRepository->upsert($fields);
                if ($this->cardTagRepository->getTags($card)) {
                    GetTags::dispatch($card)->onQueue('get-tags');
                }
            } catch (Exception $exception) {
                Log::error('Upserting tweets failed: '.$exception->getMessage());
            }
        });
    }

    public function startSaveTweetsJobs(array $raw, User $user): void
    {
        $subset = [];
        while (count($raw) > 0) {
            // We make the payload smaller here, so we don't send too much the sqs. Sqs can only hold 256 kb
            $html = (string) (new HtmlDocument())->load($raw[0], true, false)->find('[aria-label="Timeline: Bookmarks"]')[0];
            array_pop($raw);
            $subset[] = $html;
            if (2 === count($subset) || 0 === count($raw)) {
                // Run job
                TwitterBookmarks::dispatch($subset, $user);

                $subset = [];
            }
        }
    }
}
