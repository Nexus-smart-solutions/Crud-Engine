<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

/**
 * Identifies which records a bulk-delete operation should target.
 *
 * @param class-string $modelClass
 * @param array<int, int|string> $ids Already filtered/validated identifiers.
 */
final readonly class DeleteCriteria
{
    public function __construct(
        public string $modelClass,
        public array $ids,
    ) {
    }
}
