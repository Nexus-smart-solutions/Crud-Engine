<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Relations;

use Nexus\CrudEngine\DTOs\RelationSyncContext;

/**
 * One strategy per relation type (hasMany / hasOne / many-to-many).
 *
 * Each implementation owns exactly one kind of relation-sync algorithm.
 * Dispatched by {@see \Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface},
 * which is also where any recursive descent into nested relations is
 * centralized — fixing the original bug where the HasOne handler
 * recursed using the HasMany capability check by mistake.
 */
interface RelationSyncStrategyInterface
{
    public function sync(RelationSyncContext $context): void;
}
