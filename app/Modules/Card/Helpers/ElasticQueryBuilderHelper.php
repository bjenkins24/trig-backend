<?php

namespace App\Modules\Card\Helpers;

use App\Models\Person;
use App\Models\User;
use App\Modules\Person\PersonRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ElasticQueryBuilderHelper
{
    private PersonRepository $personRepo;

    public function __construct(PersonRepository $personRepo)
    {
        $this->personRepo = $personRepo;
    }

    /**
     * The user.
     */
    private function makeUserCondition(User $user): array
    {
        return [
            'match' => [
                'user_id' => $user->id,
            ],
        ];
    }

    /**
     * Check if the card belongs to the workspace. It also must be shared with
     * the whole workspace (by null permissions) or it shouldn't be accessible with
     * this condition.
     */
    private function makeWorkspaceCondition(User $user): array
    {
        return [
            'bool' => [
                'filter' => [
                    [
                        'match' => [
                            'workspace_id' => $user->workspaces()->first()->id,
                        ],
                    ],
                    [
                        'nested' => [
                            'path'  => 'permissions',
                            'query' => [
                                'bool' => [
                                    'filter' => [
                                        [
                                            'match' => [
                                                'permissions.type' => 'NULL',
                                            ],
                                        ],
                                        [
                                            'match' => [
                                                'permissions.id' => 0,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The conditions for all the teams the user is on.
     */
    private function makeTeamCondition(User $user): array
    {
        $teams = $user->teams()->select('team_id')->get();
        if ($teams->isEmpty()) {
            return [];
        }

        $result = [
            'nested' => [
                'path'  => 'permissions',
                'query' => [
                    'bool' => [
                        'should' => [],
                    ],
                ],
            ],
        ];

        $conditions = $teams->map(static function ($team) {
            return [
                'bool' => [
                    'filter' => [
                        [
                            'match' => [
                                'permissions.type' => 'App/Models/Team',
                            ],
                        ],
                        [
                            'match' => [
                                'permissions.id' => (int) $team->team_id,
                            ],
                        ],
                    ],
                ],
            ];
        });

        $result['nested']['query']['bool']['should'] = $conditions->toArray();

        return $result;
    }

    private function makeSpecificPermissionsCondition(User $user): array
    {
        $person = $this->personRepo->getByEmail($user->email);
        $personId = 0;
        if ($person) {
            $personId = $person->id;
        }

        return [
          'nested' => [
            'path'  => 'permissions',
            'query' => [
              'bool' => [
                'should' => [
                  [
                    'bool' => [
                      'filter' => [
                        [
                          'match' => [
                            'permissions.type' => User::class,
                          ],
                        ],
                        [
                          'match' => [
                            'permissions.id' => $user->id,
                          ],
                        ],
                      ],
                    ],
                  ],
                  [
                    'bool' => [
                      'filter' => [
                        [
                          'match' => [
                            'permissions.type' => Person::class,
                          ],
                        ],
                        [
                          'match' => [
                            'permissions.id' => $personId,
                          ],
                        ],
                      ],
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];
    }

    public function makePermissionsConditions(?User $user): array
    {
        if (! $user) {
            return [
                'bool' => ['should' => []],
            ];
        }

        return [
            'bool' => [
                'should' => [
                    $this->makeUserCondition($user),
                    $this->makeWorkspaceCondition($user),
//                    $this->makeTeamCondition($user),
                    $this->makeSpecificPermissionsCondition($user),
                ],
            ],
        ];
    }

    public function buildSpanCondition(string $field, string $word): array
    {
        return [
            'span_multi' => [
                'match' => [
                    'fuzzy' => [
                        $field => [
                            'value'     => $word,
                            'fuzziness' => 1,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function buildSearchCondition($constraints): ?array
    {
        $query = $constraints->get('q');
        if (! $query) {
            return [];
        }

        // Lower case the query string ES requires it to be lower case
//        $query = strtolower(str_replace('/', ' ', str_replace('-', ' ', $query)));
        $words = collect(explode(' ', $query));

        $queryTitle = $words->map(function ($word) {
            return $this->buildSpanCondition('title', $word);
        });

        $queryContent = $words->map(function ($word) {
            return $this->buildSpanCondition('content', $word);
        });

        return [
           'bool' => [
               'should' => [
                   [
                       'multi_match' => [
                           'query'  => $query,
                           'fields' => [
                               'title^3.0', 'tags^2.0', 'content^1.0',
                           ],
                           'type' => 'phrase_prefix',
//                           'analyzer' => 'filter_stemmer',
                       ],
                   ],
//                   [
//                       'span_near' => [
//                           'clauses'  => $queryTitle->toArray(),
//                           'slop'     => 10,
//                           'in_order' => false,
//                       ],
//                   ],
//                   [
//                       'span_near' => [
//                           'clauses'  => $queryContent->toArray(),
//                           'slop'     => 10,
//                           'in_order' => false,
//                       ],
//                   ],
               ],
           ],
        ];
    }

    private function makeDateConditions(Collection $constraints): array
    {
        if (! $constraints->get('s') && ! $constraints->get('e')) {
            return ['must' => []];
        }

        $base = [
            'must' => [
                'range' => [
                    'actual_created_at' => [],
                ],
            ],
        ];

        if ($constraints->get('s')) {
            $base['must']['range']['actual_created_at']['gte'] = Carbon::createFromTimestamp($constraints->get('s'))->toIso8601String();
        }

        if ($constraints->get('e')) {
            $base['must']['range']['actual_created_at']['lte'] = Carbon::createFromTimestamp($constraints->get('e'))->toIso8601String();
        }

        return $base;
    }

    private function makeTypeConditions(Collection $constraints): array
    {
        if (! $constraints->get('ty')) {
            return ['must' => []];
        }

        $types = explode(',', $constraints->get('ty'));

        $base = [
            'must' => [],
        ];

        foreach ($types as $type) {
            $base['must'][] = ['match' => ['type_tag' => $type]];
        }

        return $base;
    }

    private function makeFavoritesCondition(?User $user, Collection $constraints): array
    {
        // Cohorts
        if (! $user || ! $constraints->get('c') || ! Str::contains('favorites', $constraints->get('c'))) {
            return ['must' => []];
        }

        return [
            'must' => [
                [
                    'match' => [
                        'favorites_by_user_id' => $user->id,
                    ],
                ],
            ],
        ];
    }

    private function makeRecentlyViewedConditions(?User $user, Collection $constraints): array
    {
        // Cohorts
        if (! $user || ! $constraints->get('c') || ! Str::contains('recently-viewed', $constraints->get('c'))) {
            return ['must' => []];
        }

        return [
            'must' => [
                [
                    'nested' => [
                        'path'  => 'views',
                        'query' => [
                            'bool' => [
                                'must' => [
                                    [
                                        'range' => [
                                            'views.created_at' => [
                                                'gte' => Carbon::now()->subWeek()->timestamp,
                                            ],
                                        ],
                                    ],
                                    [
                                        'match' => [
                                            'views.user_id' => $user->id,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function makeTagConditions(Collection $constraints): array
    {
        if (! $constraints->get('t')) {
            return ['must' => []];
        }

        $tags = explode(',', $constraints->get('t'));

        $base = [
            'must' => [],
        ];

        foreach ($tags as $tag) {
            $base['must'][] = ['match' => ['tags.keyword' => $tag]];
        }

        return $base;
    }

    private function makeCollectionConditions(Collection $constraints): array
    {
        if (! $constraints->get('col')) {
            return ['must' => []];
        }

        $collections = explode(',', $constraints->get('col'));

        $base = [
            'must' => [],
        ];

        foreach ($collections as $collection) {
            $base['must'][] = ['match' => ['collections' => $collection]];
        }

        return $base;
    }

    public function baseQuery(?User $user, Collection $constraints): array
    {
        return [
            'bool' => [
                'must'   => $this->buildSearchCondition($constraints),
                'filter' => [
                    ['bool' => $this->makeDateConditions($constraints)],
                    ['bool' => $this->makeTagConditions($constraints)],
                    ['bool' => $this->makeCollectionConditions($constraints)],
                    ['bool' => $this->makeTypeConditions($constraints)],
                    ['bool' => $this->makeFavoritesCondition($user, $constraints)],
                    ['bool' => $this->makeRecentlyViewedConditions($user, $constraints)],
                    $this->makePermissionsConditions($user),
                ],
            ],
        ];
    }

    public function sortRaw(Collection $constraints): array
    {
        if ($constraints->get('c') && Str::contains($constraints->get('c'), 'recently-viewed')) {
            return [
                'views.created_at' => [
                    'order'  => 'desc',
                    'nested' => ['path' => 'views'],
                ],
            ];
        }

        return ['created_at' => 'desc'];
    }
}
