<?php

namespace App\Utils\TagParser;

class TagHeuristics
{
    /**
     * If the content, title, or URL contain a certain string let's just auto tag it. This is how a human
     * would do it.
     */
    private const HEURISTICS = [
        [
            'title' => ['Amazon.com: Books'],
            'url'   => ['audible.com'],
            'tag'   => 'Book',
        ],
        [
            'title' => ['sale'],
            'tag'   => 'Sales',
        ],
        [
            'url' => [
                'allrecipes.com',
                'yummly.com',
                'epicurious.com',
                'tasty.co',
                'spoonacular.com',
                'delish.com',
                'edamam.com',
                'pinchofyum.com',
                'recipetineats.com',
                'foodnetwork.com',
                'souldeliciouz',
                'turkishfood',
                'cooked.com',
                'gimmesomeoven.com',
                'cooking.nytimes.com',
            ],
            'tag' => 'Recipe',
        ],
        [
            'url' => [
                'washingtonpost.com',
                'cnn.com',
                ['include' => ['nytimes.com'], 'exclude' => ['cooking.nytimes.com', 'archive.nytimes.com']],
                'huffpost.com',
                'foxnews.com',
                'usatoday.com',
                'reuters.com',
                'politico.com',
                'yahoo.com/news',
                'npr.org',
                'latimes.com',
                'breitbar.com',
                'nypost.com',
                'abcnews.go.com',
                'nbcnews.com',
                'cbsnews.com',
                'newsweek.com',
                'cbsglobal.com',
                'cbslocal.com',
                'chicagotribune.com',
                'nydailynews.com',
                'denverpost.com',
                'boston.com',
                'seattletimes.com',
                'mercurynews.com',
                'washingtontimes.com',
                'miamiherald.com',
                'ktla.com',
                'theintercept.com',
                'observer.com',
                'abc7news.com',
                'gothamist.com',
                'suntimes.com',
                'wtop.com',
                'abc13.com',
                'autonews.com',
                'bostonherald.com',
                'seattlepi.com',
                'dailyherald.com',
                'wgntv.com',
                'kxan.com',
                'westword.com',
                'kdvr.com',
                'phillyvoice.com',
                'kron4.com',
                'laweekly.com',
                'twincities.com',
                'fox5sandiego.com',
                'wsvn.com',
                'wickedlocal.com',
                'miaminewtimes.com',
                'pe.com',
                'fox2now.com',
                'phoenixnewtimes.com',
                'riverfronttimes.com',
                'amny.com',
                'worldtruth.tv',
                'timesofsandiego.com',
                'whdh.com',
                'wivb.com',
                'minnpost.com',
                'metrotimes.com',
                'villagevoice.com',
                'chicagoreader.com',
                'houstonpress.com',
                'news10.com',
                'publishedreporter.com',
                'billypenn.com',
                'citylimits.org',
                'texasobserver.org',
                'nysun.com',
                'newrightnetwork.com',
                'newsstand7.com',
                'usnewsbox.com',
                'informedamerican.com',
                'miamitodaynews.com',
                'atlantaintownpaper.com',
                'usbreakingnews.net',
                'boltposts.com',
                'wolfdaily.com',
                'heartlandnewsfeed.com',
                'foxtonnews.com',
                'enmnews.com',
                'offthebus.net',
                'spooknews.com',
                'dnmedia.press',
                'foresthillstimes.com',
                'usathrill.com',
                'laindependent.com',
                'wokepatriots.com',
                'verdict.org',
                'truenewsblog.com',
                'kplr11.com',
                'marketprimenews.com',
                'americanstripe.com',
                'usnewslive247.blogspot.com',
                'magnews.live/category/us-news',
                'wfla.com',
                'nbcnewyork.com',
                'nbcchicago.com',
                'nbclosangeles.com',
                'nbcdfw.com/news',
                'nbcsandiego.com',
                'nbcphiladelphia.com',
                'nbcmiami.com',
                'nbcwashington.com',
                'stltoday.com',
                'newsday.com',
                'laobserved.com',
                'usacanadanews.com',
            ],
            'tag' => 'News',
        ],
    ];

    /**
     * Add tags based off of just simple string matching in the title or content.
     */
    public function addHeuristicTags(array $tags, ?string $title = '', ?string $content = '', ?string $url = ''): array
    {
        $newTags = $tags;
        foreach (self::HEURISTICS as $heuristic) {
            if (! empty($heuristic['title'])) {
                foreach ($heuristic['title'] as $testTitle) {
                    if (false !== stripos($title, $testTitle)) {
                        $newTags[] = $heuristic['tag'];
                    }
                }
            }
            if (! empty($heuristic['url'])) {
                foreach ($heuristic['url'] as $testUrl) {
                    if (is_array($testUrl)) {
                        foreach ($testUrl['include'] as $includeUrl) {
                            if (false !== stripos($url, $includeUrl)) {
                                $isExcluded = false;
                                foreach ($testUrl['exclude'] as $excludedUrl) {
                                    if (false !== stripos($url, $excludedUrl)) {
                                        $isExcluded = true;
                                    }
                                }
                                if (! $isExcluded) {
                                    $newTags[] = $heuristic['tag'];
                                }
                            }
                        }
                    } elseif (false !== stripos($url, $testUrl)) {
                        $newTags[] = $heuristic['tag'];
                    }
                }
            }
        }

        return $newTags;
    }

    /**
     * Remove any manually tagged heuristics from the tag array in case we need to do something
     * with _just_ the automatically generated tags.
     */
    public function removeHeuristicTags(array $tags): array
    {
        foreach (self::HEURISTICS as $heuristic) {
            foreach ($tags as $tagKey => $tag) {
                if (strtolower($heuristic['tag']) === strtolower($tag)) {
                    unset($tags[$tagKey]);
                }
            }
        }

        return $tags;
    }
}
