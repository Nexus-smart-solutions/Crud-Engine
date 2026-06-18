<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Illuminate\Http\Request;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Services\ArticleStoreService;
use Nexus\CrudEngine\Tests\TestCase;

final class StoreServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_store_creates_a_record_and_returns_a_success_result(): void
    {
        $this->app->instance('request', Request::create('/articles', 'POST', [
            'title' => 'My first article',
            'body' => 'Some content',
        ]));

        /** @var ArticleStoreService $service */
        $service = $this->app->make(ArticleStoreService::class);

        $result = $service->store();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(201, $result->code);
        $this->assertSame(1, Article::where('title', 'My first article')->count());
    }

    public function test_store_syncs_declared_relations_from_a_single_request(): void
    {
        $this->app->instance('request', Request::create('/articles', 'POST', [
            'title' => 'Article with comments',
            'comments' => [
                ['body' => 'First comment'],
                ['body' => 'Second comment'],
            ],
        ]));

        /** @var ArticleStoreService $service */
        $service = $this->app->make(ArticleStoreService::class);

        $result = $service->store();

        $article = Article::find($result->data['id']);

        $this->assertSame(2, $article->comments()->count());
    }

    public function test_store_rejects_fields_not_declared_in_the_request_rules(): void
    {
        // Bug 4.1 regression at the Crud-service level, not just the
        // validator unit level: an undeclared field must never reach
        // Model::create().
        $this->app->instance('request', Request::create('/articles', 'POST', [
            'title' => 'Safe title',
            'is_admin' => true,
        ]));

        /** @var ArticleStoreService $service */
        $service = $this->app->make(ArticleStoreService::class);

        $service->store();

        $article = Article::where('title', 'Safe title')->first();

        $this->assertArrayNotHasKey('is_admin', $article->getAttributes());
    }
}
