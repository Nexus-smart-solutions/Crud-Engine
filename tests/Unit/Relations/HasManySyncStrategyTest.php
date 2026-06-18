<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Relations;

use Nexus\CrudEngine\DTOs\Enums\RelationType;
use Nexus\CrudEngine\DTOs\RelationSyncContext;
use Nexus\CrudEngine\Strategies\Relations\HasManySyncStrategy;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\TestCase;

final class HasManySyncStrategyTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_creates_rows_with_no_id_and_keeps_rows_with_a_matching_id(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $existingComment = $article->comments()->create(['body' => 'Existing comment']);

        $strategy = $this->app->make(HasManySyncStrategy::class);

        $strategy->sync(new RelationSyncContext(
            model: $article,
            relationName: 'comments',
            incomingData: [
                ['id' => $existingComment->id, 'body' => 'Updated comment'],
                ['body' => 'Brand new comment'],
            ],
            type: RelationType::HasMany,
        ));

        $this->assertSame(2, $article->comments()->count());
        $this->assertSame('Updated comment', $existingComment->refresh()->body);
        $this->assertSame(1, $article->comments()->where('body', 'Brand new comment')->count());
    }

    public function test_deletes_rows_whose_id_is_absent_from_the_incoming_payload(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $keep = $article->comments()->create(['body' => 'Keep me']);
        $remove = $article->comments()->create(['body' => 'Remove me']);

        $strategy = $this->app->make(HasManySyncStrategy::class);

        $strategy->sync(new RelationSyncContext(
            model: $article,
            relationName: 'comments',
            incomingData: [
                ['id' => $keep->id, 'body' => 'Keep me'],
            ],
            type: RelationType::HasMany,
        ));

        $this->assertSame(1, $article->comments()->count());
        $this->assertNull($article->comments()->find($remove->id));
    }

    public function test_empty_incoming_data_deletes_all_existing_rows(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $article->comments()->create(['body' => 'One']);
        $article->comments()->create(['body' => 'Two']);

        $strategy = $this->app->make(HasManySyncStrategy::class);

        $strategy->sync(new RelationSyncContext(
            model: $article,
            relationName: 'comments',
            incomingData: [],
            type: RelationType::HasMany,
        ));

        $this->assertSame(0, $article->comments()->count());
    }
}
