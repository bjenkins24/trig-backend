<?php

namespace Tests\Feature\Modules\Tag;

use App\Models\Tag;
use App\Modules\Tag\TagRepository;
use Tests\TestCase;

class TagRepositoryTest extends TestCase
{
    // This test is valuable if we use a non-mocked version of elastic search which I haven't set up in our test suite
    // Test locally if you need to. That's why this still exists

//    public function seedTags(): void
//    {
//        $this->refreshDb();
//        Tag::create([
//            'workspace_id' => 1,
//            'tag'          => 'the games',
//        ]);
//        Tag::create([
//            'workspace_id' => 1,
//            'tag'          => 'online games',
//        ]);
//        Tag::create([
//            'workspace_id' => 1,
//            'tag'          => 'product management articles',
//        ]);
//        Tag::create([
//            'workspace_id' => 1,
//            'tag'          => 'software developments',
//        ]);
//
//    }

//    public function testSeed() {
//        $this->seedTags();
//    }

//    /**
//     * @dataProvider tagMatchesProvider
//     * @group n
//     */
//    public function testFindSimilar(string $query, bool $exists): void
//    {
//        $this->seedTags();
//
//        $tag = app(TagRepository::class)->findSimilar($query, 1);
//
//        self::assertEquals(null !== $tag, $exists);
//    }
//
//    public function tagMatchesProvider(): array
//    {
//        return [
//            ['games', true],
//            ['new games', false],
//            ['online games minecraft', false],
//            ['product management', false],
//            ['software development', true],
//        ];
//    }
}
