<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Nexus\CrudEngine\Events\RecordDeleted;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Services\ArticleDeleteService;
use Nexus\CrudEngine\Tests\TestCase;

final class DeleteServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
        Storage::fake('testing');
    }

    public function test_delete_removes_the_record_and_dispatches_record_deleted(): void
    {
        Event::fake([RecordDeleted::class]);

        $article = Article::create(['title' => 'To be deleted']);

        /** @var ArticleDeleteService $service */
        $service = $this->app->make(ArticleDeleteService::class)
            ->forTargets(new Collection([$article]));

        $result = $service->delete();

        $this->assertTrue($result->isSuccessful());
        $this->assertNull(Article::find($article->id));
        Event::assertDispatched(RecordDeleted::class);
    }

    public function test_delete_returns_error_when_no_targets_resolved(): void
    {
        /** @var ArticleDeleteService $service */
        $service = $this->app->make(ArticleDeleteService::class)
            ->forTargets(new Collection());

        $result = $service->delete();

        $this->assertFalse($result->isSuccessful());
        $this->assertSame(404, $result->code);
    }

    public function test_delete_also_removes_associated_files(): void
    {
        $article = Article::create(['title' => 'Has a cover', 'cover_image' => 'cover.jpg']);
        Storage::disk('testing')->put("articles/{$article->id}/cover.jpg", 'fake-bytes');

        /** @var ArticleDeleteService $service */
        $service = $this->app->make(ArticleDeleteService::class)
            ->forTargets(new Collection([$article]));

        $service->delete();

        Storage::disk('testing')->assertMissing("articles/{$article->id}/cover.jpg");
    }
}
