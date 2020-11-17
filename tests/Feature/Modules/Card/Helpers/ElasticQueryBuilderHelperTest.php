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
            'organization_id' => 1,
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
                                        'organization_id' => 1,
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
}
