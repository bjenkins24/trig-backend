<?php

namespace Tests\Feature\Modules\Card;

use App\Models\User;
use App\Modules\Card\Helpers\ElasticQueryBuilderHelper;
use Tests\TestCase;

class ElasticQueryBuilderHelperTest extends TestCase
{
    public function testMakePermissionsConditions()
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
                                                            'permissions.type' => 'App/Models/Team',
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
                                                            'permissions.type' => 'App\Models\User',
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
                                                            'permissions.type' => 'App\Models\Person',
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

        $this->assertEquals($expected, $result);
    }
}
