<?php

namespace Tests\Feature\Modules\Card\Helpers;

use App\Models\Person;
use App\Models\User;
use App\Modules\Card\Helpers\ElasticQueryBuilderHelper;
use Tests\TestCase;

class ElasticQueryBuilderHelperTest extends TestCase
{
    public function testMakePermissionsConditions(): void
    {
        User::find(1)->teams()->create([
            'workspace_id'    => 1,
            'name'            => 'my team name',
        ]);

        $result = app(ElasticQueryBuilderHelper::class)->makePermissionsConditions(User::find(1));

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'match' => [
                            'user_id' => 1,
                        ],
                    ],
                    [
                        'bool' => [
                            'filter' => [
                                [
                                    'match' => [
                                        'workspace_id' => 1,
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
                    ],
                    [
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
                                                            'permissions.id' => 1,
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
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, $result);
    }

    public function testCondition(): void
    {
        $constraints = collect(['q' => 'elasticsearch']);
        $result = app(ElasticQueryBuilderHelper::class)->baseQuery(User::find(1), $constraints);
        dd(json_encode($result));
    }

    public function testBuildSearchCondition(): void
    {
        $result = app(ElasticQueryBuilderHelper::class)->buildSearchCondition(collect(['q' => 'my cool house-at']));

        $expected = [
            'bool' => [
                'should' => [
                    [
                        'span_near' => [
                            'clauses' => [
                                [
                                    'span_multi' => [
                                        'match' => [
                                            'fuzzy' => [
                                                'title' => 'my',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'span_multi' => [
                                        'match' => [
                                            'fuzzy' => [
                                                'title' => 'cool',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'span_multi' => [
                                        'match' => [
                                            'fuzzy' => [
                                                'title' => 'house',
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'span_multi' => [
                                        'match' => [
                                            'fuzzy' => [
                                                'title' => 'at',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            'slop'     => 10,
                            'in_order' => false,
                        ],
                        [
                            'span_near' => [
                                'clauses' => [
                                    [
                                        'span_multi' => [
                                            'match' => [
                                                'fuzzy' => [
                                                    'title' => 'my',
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'span_multi' => [
                                            'match' => [
                                                'fuzzy' => [
                                                    'title' => 'cool',
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'span_multi' => [
                                            'match' => [
                                                'fuzzy' => [
                                                    'title' => 'house',
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'span_multi' => [
                                            'match' => [
                                                'fuzzy' => [
                                                    'title' => 'at',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                'slop'     => 20,
                                'in_order' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertEquals($expected, $result);
    }
}
