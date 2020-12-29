<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateCardsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::create('cards', static function (Mapping $mapping, Settings $settings) {
            $mapping->long('user_id');
            $mapping->keyword('card_type');
            $mapping->keyword('url');
            $mapping->text('tags', [
                'fields' => [
                    'keyword' => [
                        'type' => 'keyword', 'ignore_above' => 256,
                    ],
                ],
            ]);
            $mapping->long('workspace_id');
            $mapping->text('title', [
                'fields' => [
                    'keyword' => [
                          'type' => 'keyword', 'ignore_above' => 256,
                    ],
                ],
            ]);
            $mapping->text('content', [
                'term_vector' => 'with_positions_offsets',
            ]);
            $mapping->keyword('card_duplicate_ids');
            $mapping->nested('views', [
                'properties' => [
                    'user_id' => [
                        'type'       => 'long',
                        'null_value' => 0,
                    ],
                    'created_at' => [
                        'type'       => 'date',
                        'null_value' => 'NULL',
                    ],
                ],
            ]);
            $mapping->long('favorites_by_user_id');
            $mapping->nested('permissions', [
                'properties' => [
                    'id' => [
                        'type'       => 'long',
                        'null_value' => 0,
                    ],
                    'type' => [
                        'type'       => 'keyword',
                        'null_value' => 'NULL',
                    ],
                ],
            ]);
            $mapping->date('actual_created_at');

            $settings->analysis([
                'filter' => [
                    'stemmers' => [
                        'type'     => 'stemmer',
                        'language' => 'english',
                    ],
                ],
                'analyzer' => [
                    'filter_stemmer' => [
                        'filter' => [
                            'lowercase', 'stemmers',
                        ],
                        'tokenizer' => 'standard',
                    ],
                ],
            ]);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Index::dropIfExists('cards');
    }
}
