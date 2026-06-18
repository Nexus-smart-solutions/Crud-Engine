<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

use Nexus\CrudEngine\DTOs\Enums\FileOperationType;

/**
 * Result of a single file lifecycle operation (store/delete/skip),
 * returned by {@see \Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface}.
 */
final readonly class FileOperation
{
    public function __construct(
        public FileOperationType $type,
        public string $attribute,
        public ?string $fileName = null,
        public ?string $url = null,
    ) {
    }
}
