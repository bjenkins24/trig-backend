<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class ScreenshotLargeFields implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::putMapping('cards', static function (Mapping $mapping) {
            $mapping->keyword('screenshot_thumbnail_large');
            $mapping->short('screenshot_thumbnail_large_width');
            $mapping->short('screenshot_thumbnail_large_height');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
