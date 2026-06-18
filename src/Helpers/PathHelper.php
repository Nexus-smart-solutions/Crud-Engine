<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Helpers;

/**
 * Pure utility functions only — no shared state, no I/O, nothing that
 * needs to be mocked in a test. This is distinct from the "static helper
 * classes" the Phase 1 audit flagged as an anti-pattern: those static
 * classes performed I/O and business logic (`StoragePictures::storeFile()`
 * touched the filesystem); these methods are deterministic transformations
 * of their inputs only.
 */
final class PathHelper
{
    private function __construct()
    {
        // Not instantiable — pure static utility class.
    }

    public static function joinPath(string $directory, string $fileName): string
    {
        return trim($directory, '/').'/'.ltrim($fileName, '/');
    }

    public static function normalizeDirectory(string $directory): string
    {
        return trim($directory, '/');
    }
}
