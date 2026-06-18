<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Files;

use Illuminate\Http\UploadedFile;
use Nexus\CrudEngine\Strategies\Files\OriginalFilenameStrategy;
use Nexus\CrudEngine\Tests\Fixtures\Models\Document;
use Nexus\CrudEngine\Tests\TestCase;

/**
 * Regression tests for Security Finding S4 from the Phase 1 audit: the
 * original `StoragePictures::storeFile()` used the client-supplied
 * original filename almost verbatim, allowing path-traversal sequences,
 * null bytes, and other unsafe characters to flow into the stored path.
 */
final class OriginalFilenameStrategyTest extends TestCase
{
    private OriginalFilenameStrategy $strategy;
    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new OriginalFilenameStrategy();
        $this->document = new Document();
    }

    public function test_strips_directory_traversal_sequences(): void
    {
        $file = $this->fakeFileNamed('../../../etc/passwd.jpg');

        $name = $this->strategy->generateName($file, $this->document);

        $this->assertStringNotContainsString('..', $name);
        $this->assertStringNotContainsString('/', $name);
        $this->assertSame('passwd.jpg', $name);
    }

    public function test_strips_control_characters_and_null_bytes(): void
    {
        $file = $this->fakeFileNamed("evil\x00name.jpg");

        $name = $this->strategy->generateName($file, $this->document);

        $this->assertStringNotContainsString("\x00", $name);
        $this->assertSame('evilname.jpg', $name);
    }

    public function test_strips_characters_outside_the_allow_list(): void
    {
        $file = $this->fakeFileNamed('weird;rm -rf$(name).jpg');

        $name = $this->strategy->generateName($file, $this->document);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]+$/', $name);
    }

    public function test_replaces_spaces_with_underscores_preserving_original_behavior(): void
    {
        $file = $this->fakeFileNamed('my holiday photo.jpg');

        $name = $this->strategy->generateName($file, $this->document);

        $this->assertSame('my_holiday_photo.jpg', $name);
    }

    public function test_falls_back_to_a_hashed_name_when_sanitization_leaves_nothing_usable(): void
    {
        $file = $this->fakeFileNamed('../../..');

        $name = $this->strategy->generateName($file, $this->document);

        $this->assertNotSame('', $name);
    }

    private function fakeFileNamed(string $originalName): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'crud-engine-test-');

        return new UploadedFile($tempPath, $originalName, 'image/jpeg', null, true);
    }
}
