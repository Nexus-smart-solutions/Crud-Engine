<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\DTOs\Enums\FileOperationType;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\TestCase;

final class FileLifecycleServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
        Storage::fake('testing');
    }

    public function test_store_persists_the_file_and_sets_the_attribute(): void
    {
        $article = Article::create(['title' => 'Hello world']);

        $file = UploadedFile::fake()->create('cover.jpg', 10);

        /** @var FileLifecycleServiceInterface $service */
        $service = $this->app->make(FileLifecycleServiceInterface::class);

        $operation = $service->store($article, 'cover_image', $file);

        $this->assertSame(FileOperationType::Stored, $operation->type);
        $this->assertNotNull($operation->fileName);
        Storage::disk('testing')->assertExists("articles/{$article->id}/{$operation->fileName}");
        $this->assertSame($operation->fileName, $article->refresh()->cover_image);
    }

    /**
     * Regression test for Bug 4.2: the original `FilesHandleForCrud`
     * deleted the physical file but never nulled out the database
     * column, leaving a dangling reference. This asserts both halves of
     * the fix happen together.
     */
    public function test_delete_removes_the_file_and_nulls_the_database_attribute(): void
    {
        $article = Article::create(['title' => 'Hello world']);

        $file = UploadedFile::fake()->create('cover.jpg', 10);

        /** @var FileLifecycleServiceInterface $service */
        $service = $this->app->make(FileLifecycleServiceInterface::class);

        $stored = $service->store($article, 'cover_image', $file);
        Storage::disk('testing')->assertExists("articles/{$article->id}/{$stored->fileName}");

        $service->delete($article, 'cover_image');

        Storage::disk('testing')->assertMissing("articles/{$article->id}/{$stored->fileName}");

        // Re-fetch from the database — not just the in-memory instance —
        // to prove the null was actually persisted, not only set on the
        // object.
        $this->assertNull($article->refresh()->cover_image);
    }

    public function test_apply_incoming_value_with_uploaded_file_stores_it(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $file = UploadedFile::fake()->create('cover.jpg', 10);

        /** @var FileLifecycleServiceInterface $service */
        $service = $this->app->make(FileLifecycleServiceInterface::class);

        $operation = $service->applyIncomingValue($article, 'cover_image', $file);

        $this->assertNotNull($operation);
        $this->assertSame(FileOperationType::Stored, $operation->type);
    }

    public function test_apply_incoming_value_with_null_deletes_existing_file(): void
    {
        $article = Article::create(['title' => 'Hello world']);
        $file = UploadedFile::fake()->create('cover.jpg', 10);

        /** @var FileLifecycleServiceInterface $service */
        $service = $this->app->make(FileLifecycleServiceInterface::class);

        $service->store($article, 'cover_image', $file);
        $operation = $service->applyIncomingValue($article, 'cover_image', null);

        $this->assertNotNull($operation);
        $this->assertSame(FileOperationType::Deleted, $operation->type);
        $this->assertNull($article->refresh()->cover_image);
    }

    public function test_apply_incoming_value_with_unrelated_scalar_is_a_no_op(): void
    {
        $article = Article::create(['title' => 'Hello world', 'cover_image' => 'existing.jpg']);

        /** @var FileLifecycleServiceInterface $service */
        $service = $this->app->make(FileLifecycleServiceInterface::class);

        $operation = $service->applyIncomingValue($article, 'cover_image', 'existing.jpg');

        $this->assertNull($operation);
        $this->assertSame('existing.jpg', $article->refresh()->cover_image);
    }
}
