<?php

namespace App\Modules\Card\Helpers;

use App\Models\Person;
use App\Models\User;
use App\Modules\Person\PersonRepository;
use Illuminate\Support\Collection;

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
     * Check if the card belongs to the organization. It also must be shared with
     * the whole organization (by null permissions) or it shouldn't be accessible with
     * this condition.
     */
    private function makeOrganizationCondition(User $user): array
    {
        return [
            'bool' => [
                'filter' => [
                    [
                        'match' => [
                            'organization_id' => $user->organizations()->first()->id,
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

    public function makePermissionsConditions(User $user): array
    {
        return [
            'bool' => [
                'should' => [
                    $this->makeUserCondition($user),
                    $this->makeOrganizationCondition($user),
//                    $this->makeTeamCondition($user),
                    $this->makeSpecificPermissionsCondition($user),
                ],
            ],
        ];
    }

    public function buildSearchCondition($constraints): ?array
    {
        if (! $constraints->get('q')) {
            return [];
        }

        return [
            'simple_query_string' => [
                'query'  => $constraints->get('q'),
                'fields' => ['title', 'content'],
            ],
        ];
    }

    public function baseQuery(User $user, Collection $constraints): array
    {
        return [
            'bool' => [
                'must'   => $this->buildSearchCondition($constraints),
                'filter' => [
                    $this->makePermissionsConditions($user),
                ],
            ],
        ];
    }
}
