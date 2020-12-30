<?php

declare(strict_types=1);

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateTagsIndex implements MigrationInterface
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Index::create('tags', static function (Mapping $mapping, Settings $settings) {
            $mapping->long('workspace_id');
            $mapping->text('tag', [
                'analyzer' => 'tags_analyzer',
            ]);
            $settings->analysis([
                'filter' => [
                    'filter_stop' => [
                        'type'        => 'stop',
                        'ignore_case' => true,
                        'stopwords'   => [
                            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'for',
                            'if', 'in', 'into', 'is', 'it', 'of', 'on', 'or', 'such', 'that',
                            'the', 'their', 'then', 'there', 'these', 'they', 'this', 'to',
                            'was', 'will', 'with',
                        ],
                    ],
                ],
                'analyzer' => [
                    'tags_analyzer' => [
                        'tokenizer' => 'standard',
                        'filter'    => [
                            'lowercase',
                            'asciifolding',
                            'filter_stop',
                            'stemmer',
                        ],
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
        Index::dropIfExists('tags');
    }
}
