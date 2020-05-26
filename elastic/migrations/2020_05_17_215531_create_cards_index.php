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
        Index::create('cards', function (Mapping $mapping, Settings $settings) {
            $mapping->long('user_id');
            $mapping->long('card_type_id');
            $mapping->long('organization_id');
            $mapping->text('title', [
                'fields' => [
                    'keyword' => [
                          'type' => 'keyword', 'ignore_above' => 256,
                    ],
                ],
            ]);
            $mapping->text('doc_title', [
                'fields' => [
                    'keyword' => [
                          'type' => 'keyword', 'ignore_above' => 256,
                    ],
                ],
            ]);
            $mapping->text('content');
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
