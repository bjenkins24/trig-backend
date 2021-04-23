<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class AddTypeTag implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::putMapping('cards', static function (Mapping $mapping) {
            $mapping->keyword('type_tag');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
