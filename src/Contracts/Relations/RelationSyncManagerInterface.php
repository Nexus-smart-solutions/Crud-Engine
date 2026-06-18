<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Relations;

use Illuminate\Database\Eloquent\Model;

/**
 * Orchestrates relation syncing for a model: inspects which relation
 * capabilities the model has (via the {@see \Nexus\CrudEngine\Contracts\CapabilityRegistryInterface}),
 * and dispatches each declared relation to the correct
 * {@see RelationSyncStrategyInterface}.
 *
 * Replaces `App\Core\Traits\RelationsHandleForCrud` and the three static
 * `HandleRelation*` classes from the original codebase.
 */
interface RelationSyncManagerInterface
{
    /**
     * Sync every relation declared on the model that has a matching key
     * present in $data, recursing into nested relations as needed.
     *
     * @param int $depth Current recursion depth; callers recursing into
     *                    nested relations must pass the parent depth + 1
     *                    so {@see RelationSyncStrategyInterface} implementations
     *                    can guard against runaway recursion.
     */
    public function syncAll(Model $model, array $data, int $depth = 0): void;
}
