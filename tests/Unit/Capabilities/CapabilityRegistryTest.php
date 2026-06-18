<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Capabilities;

use Nexus\CrudEngine\Services\Capabilities\CapabilityRegistry;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Models\Document;
use Nexus\CrudEngine\Tests\Fixtures\Models\Profile;
use Nexus\CrudEngine\Tests\Fixtures\Models\Tag;
use Nexus\CrudEngine\Tests\TestCase;

final class CapabilityRegistryTest extends TestCase
{
    private CapabilityRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new CapabilityRegistry();
    }

    public function test_article_supports_file_upload_has_many_has_one_and_many_to_many(): void
    {
        $article = new Article();

        $this->assertTrue($this->registry->supportsFileUpload($article));
        $this->assertTrue($this->registry->supportsHasMany($article));
        $this->assertTrue($this->registry->supportsHasOne($article));
        $this->assertTrue($this->registry->supportsManyToMany($article));
    }

    public function test_profile_supports_has_one_but_not_has_many(): void
    {
        $profile = new Profile();

        $this->assertTrue($this->registry->supportsHasOne($profile));
        $this->assertFalse($this->registry->supportsHasMany($profile));
    }

    public function test_plain_model_supports_nothing(): void
    {
        $tag = new Tag();

        $this->assertFalse($this->registry->supportsFileUpload($tag));
        $this->assertFalse($this->registry->supportsHasMany($tag));
        $this->assertFalse($this->registry->supportsHasOne($tag));
        $this->assertFalse($this->registry->supportsManyToMany($tag));
        $this->assertFalse($this->registry->usesOriginalFilename($tag));
    }

    public function test_document_uses_original_filename(): void
    {
        $document = new Document();

        $this->assertTrue($this->registry->usesOriginalFilename($document));
    }
}
