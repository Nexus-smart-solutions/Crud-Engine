<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * Typed pairing of the model being updated and its already-validated
 * incoming attributes.
 *
 * @param array<string, mixed> $attributes Already-validated attributes only.
 */
final readonly class UpdateContext
{
    public function __construct(
        public Model $model,
        public array $attributes,
    ) {
    }
}
