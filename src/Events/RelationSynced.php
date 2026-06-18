<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\DTOs\Enums\RelationType;

/**
 * Dispatched after a single relation has been synced on a model.
 */
final class RelationSynced
{
    public function __construct(
        public readonly Model $model,
        public readonly string $relationName,
        public readonly RelationType $type,
    ) {
    }
}
