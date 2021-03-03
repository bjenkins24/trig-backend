<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class ScreenshotFields implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::putMapping('cards', static function (Mapping $mapping) {
            $mapping->keyword('screenshot_thumbnail');
            $mapping->short('screenshot_thumbnail_width');
            $mapping->short('screenshot_thumbnail_height');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
