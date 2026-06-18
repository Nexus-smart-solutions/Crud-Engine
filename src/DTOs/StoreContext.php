<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

/**
 * Typed replacement for the raw `array $data` that used to flow through
 * 4-5 layers (getDataHandle → handleFileInData → handleRelationInData →
 * HandleRelation*) in the original implementation.
 *
 * @param class-string $modelClass
 * @param array<string, mixed> $attributes Already-validated attributes only.
 */
final readonly class StoreContext
{
    public function __construct(
        public string $modelClass,
        public array $attributes,
    ) {
    }

    public function withAttributes(array $attributes): self
    {
        return new self($this->modelClass, $attributes);
    }
}
