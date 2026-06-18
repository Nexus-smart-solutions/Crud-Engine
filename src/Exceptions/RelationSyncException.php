<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Exceptions;

/**
 * Thrown by relation sync strategies when a nested relation cannot be
 * resolved or recursion would exceed the configured maximum depth
 * (`crud-engine.relations.max_recursion_depth`).
 */
class RelationSyncException extends CrudEngineException
{
    public static function relationMethodMissing(string $relationName, string $modelClass): self
    {
        return new self("Relation method [{$relationName}] does not exist on model [{$modelClass}].");
    }

    public static function maxRecursionDepthExceeded(string $relationName, int $maxDepth): self
    {
        return new self("Relation sync for [{$relationName}] exceeded the maximum recursion depth of {$maxDepth}.");
    }
}
