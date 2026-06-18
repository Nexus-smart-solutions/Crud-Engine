<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Relations;

use Nexus\CrudEngine\Contracts\Relations\RelationSyncStrategyInterface;
use Nexus\CrudEngine\DTOs\RelationSyncContext;
use Nexus\CrudEngine\Exceptions\RelationSyncException;

/**
 * Thin, safe wrapper over Eloquent's `sync()` for many-to-many relations.
 *
 * Replaces the original static `HandleRelationManyToMany` class. Fixes
 * the original's fragile `$data[0] == null` check (which emitted a PHP
 * warning on an empty array and used loose comparison) with an explicit,
 * type-safe normalization step.
 */
final class ManyToManySyncStrategy implements RelationSyncStrategyInterface
{
    public function sync(RelationSyncContext $context): void
    {
        $model = $context->model;
        $relationName = $context->relationName;

        if (! method_exists($model, $relationName)) {
            throw RelationSyncException::relationMethodMissing($relationName, $model::class);
        }

        $model->{$relationName}()->sync($this->normalizeIds($context->incomingData));
    }

    /**
     * @return array<int, int|string>
     */
    private function normalizeIds(mixed $incomingData): array
    {
        if (! is_array($incomingData)) {
            return [];
        }

        return array_values(array_filter(
            $incomingData,
            static fn ($id) => $id !== null && $id !== '',
        ));
    }
}
