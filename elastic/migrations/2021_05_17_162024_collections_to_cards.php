<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CollectionsToCards implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::putMapping('cards', static function (Mapping $mapping) {
            $mapping->long('collections');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
