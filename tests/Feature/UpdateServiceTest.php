<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Illuminate\Http\Request;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Services\ArticleUpdateService;
use Nexus\CrudEngine\Tests\TestCase;

final class UpdateServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_update_persists_changes_and_returns_a_success_result(): void
    {
        $article = Article::create(['title' => 'Original title']);

        $this->app->instance('request', Request::create('/articles/1', 'PUT', [
            'title' => 'Updated title',
        ]));

        /** @var ArticleUpdateService $service */
        $service = $this->app->make(ArticleUpdateService::class)->forArticle($article);

        $result = $service->update();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame('Updated title', $article->refresh()->title);
    }

    public function test_update_syncs_nested_relations(): void
    {
        $article = Article::create(['title' => 'Original title']);
        $existing = $article->comments()->create(['body' => 'Old comment']);

        $this->app->instance('request', Request::create('/articles/1', 'PUT', [
            'title' => 'Original title',
            'comments' => [
                ['id' => $existing->id, 'body' => 'Edited comment'],
            ],
        ]));

        /** @var ArticleUpdateService $service */
        $service = $this->app->make(ArticleUpdateService::class)->forArticle($article);

        $service->update();

        $this->assertSame('Edited comment', $existing->refresh()->body);
        $this->assertSame(1, $article->comments()->count());
    }
}
