<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Illuminate\Http\Request;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Services\ArticleBulkDeleteService;
use Nexus\CrudEngine\Tests\TestCase;

final class BulkDeleteServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_bulk_delete_removes_all_matching_ids(): void
    {
        $first = Article::create(['title' => 'One']);
        $second = Article::create(['title' => 'Two']);
        $survivor = Article::create(['title' => 'Three']);

        $this->app->instance('request', Request::create('/articles', 'DELETE', [
            'ids' => [$first->id, $second->id],
        ]));

        /** @var ArticleBulkDeleteService $service */
        $service = $this->app->make(ArticleBulkDeleteService::class);

        $result = $service->delete();

        $this->assertTrue($result->isSuccessful());
        $this->assertNull(Article::find($first->id));
        $this->assertNull(Article::find($second->id));
        $this->assertNotNull(Article::find($survivor->id));
    }

    /**
     * Regression test for Bug 4.5: the original `BulkDestroyService`
     * called `array_filter($ids, 'is_numeric')` directly on
     * `request()->input('ids') ?? []` with no check that the input was
     * actually an array — a scalar `ids` value (e.g. `?ids=5`) threw a
     * TypeError. This must not throw.
     */
    public function test_a_scalar_ids_value_does_not_throw_and_is_treated_as_a_single_id(): void
    {
        $article = Article::create(['title' => 'Solo target']);

        $this->app->instance('request', Request::create('/articles', 'DELETE', [
            'ids' => (string) $article->id,
        ]));

        /** @var ArticleBulkDeleteService $service */
        $service = $this->app->make(ArticleBulkDeleteService::class);

        $result = $service->delete();

        $this->assertTrue($result->isSuccessful());
        $this->assertNull(Article::find($article->id));
    }

    public function test_missing_ids_input_resolves_to_an_empty_set_and_returns_an_error(): void
    {
        $this->app->instance('request', Request::create('/articles', 'DELETE', []));

        /** @var ArticleBulkDeleteService $service */
        $service = $this->app->make(ArticleBulkDeleteService::class);

        $result = $service->delete();

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(404, $result->code);
    }
}
