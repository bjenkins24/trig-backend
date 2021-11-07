<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class TweetFields implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::putMapping('cards', static function (Mapping $mapping) {
            $mapping->text('twitter_name');
            $mapping->text('twitter_handle');
            $mapping->text('twitter_avatar');
            $mapping->text('twitter_image_1');
            $mapping->text('twitter_image_2');
            $mapping->text('twitter_image_3');
            $mapping->text('twitter_image_4');
            $mapping->text('twitter_reply_name');
            $mapping->text('twitter_reply_handle');
            $mapping->text('twitter_reply_avatar');
            $mapping->text('twitter_reply_replying_to');
            $mapping->text('twitter_reply_content');
            $mapping->text('twitter_link_href');
            $mapping->text('twitter_link_image_src');
            $mapping->text('twitter_link_url');
            $mapping->text('twitter_link_title');
            $mapping->text('twitter_link_description');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
    }
}
