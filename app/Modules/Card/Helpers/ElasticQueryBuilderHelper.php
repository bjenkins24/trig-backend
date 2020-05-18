<?php

namespace App\Modules\Card\Helpers;

use App\Models\User;
use App\Modules\Person\PersonRepository;

class ElasticQueryBuilderHelper
{
    private PersonRepository $personRepo;

    public function __construct(PersonRepository $personRepo)
    {
        $this->personRepo = $personRepo;
    }

    public function baseQuery(User $user)
    {
        $person = $this->personRepo->getByEmail($user->email);
        $personId = 0;
        if ($person) {
            $personId = $person->id;
        }

        return [
            'bool' => [
                'filter' => [
                    [
                        // ONE of these has to match
                        'bool' => [
                            'should' => [
                                [
                                    'match' => [
                                        'organization_id' => $user->organizations()->first()->id,
                                    ],
                                ],
                                [
                                    'match' => [
                                        'user_id' => $user->id,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'should' => [
                    [
                        'nested' => [
                            'path'  => 'permissions',
                            'query' => [
                                'bool' => [
                                    // One of these has to match
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
                    ],
                ],
            ],
        ];
    }
}
