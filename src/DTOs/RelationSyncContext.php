<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\DTOs\Enums\RelationType;

/**
 * Everything a {@see \Nexus\CrudEngine\Contracts\Relations\RelationSyncStrategyInterface}
 * needs to sync one relation on one model.
 *
 * `$depth` lets {@see \Nexus\CrudEngine\Services\Relations\RelationSyncManager}
 * guard against runaway recursion when nested relations reference each
 * other; the original implementation had no such guard.
 */
final readonly class RelationSyncContext
{
    public function __construct(
        public Model $model,
        public string $relationName,
        public mixed $incomingData,
        public RelationType $type,
        public int $depth = 0,
    ) {
    }
}
