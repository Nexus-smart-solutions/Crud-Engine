<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Relations;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncStrategyInterface;
use Nexus\CrudEngine\DTOs\RelationSyncContext;
use Nexus\CrudEngine\Exceptions\RelationSyncException;

/**
 * Diff-based sync for a "has many" relation: incoming rows with an `id`
 * are updated, rows without one are created, and existing rows whose id
 * is absent from the incoming payload are deleted. Handles file fields
 * and recurses into nested relations on each child row.
 *
 * Replaces the original static `HandleRelationHasMany` class. Fixes the
 * N+1 query pattern flagged in the Phase 1 audit: existing related rows
 * are bulk-loaded once via `whereIn`, instead of one `find()` call per
 * incoming row.
 *
 * Note on the `Container` dependency: this strategy needs to recurse
 * back into {@see RelationSyncManagerInterface} for nested relations,
 * but `RelationSyncManager` itself depends on this strategy to handle
 * "has many" relations — a genuine circular dependency between the two
 * services. Constructor-injecting `RelationSyncManagerInterface`
 * directly would make the container recurse infinitely while building
 * the singleton. Injecting the container and resolving the manager
 * lazily inside {@see handleChildRecursion()} (i.e. only once this
 * object is actually being used, by which point the manager singleton
 * already exists) breaks the cycle without resorting to a static call.
 */
final class HasManySyncStrategy implements RelationSyncStrategyInterface
{
    public function __construct(
        private readonly CapabilityRegistryInterface $capabilities,
        private readonly FileLifecycleServiceInterface $files,
        private readonly Container $container,
        private readonly int $maxRecursionDepth,
    ) {
    }

    public function sync(RelationSyncContext $context): void
    {
        if ($context->depth > $this->maxRecursionDepth) {
            throw RelationSyncException::maxRecursionDepthExceeded($context->relationName, $this->maxRecursionDepth);
        }

        $model = $context->model;
        $relationName = $context->relationName;

        if (! method_exists($model, $relationName)) {
            throw RelationSyncException::relationMethodMissing($relationName, $model::class);
        }

        $incomingRows = $this->normalizeIncomingRows($context->incomingData);

        /** @var \Illuminate\Database\Eloquent\Relations\HasMany $relation */
        $relation = $model->{$relationName}();

        $incomingIds = array_values(array_filter(
            array_map(static fn (array $row) => $row['id'] ?? null, $incomingRows),
            static fn ($id) => $id !== null,
        ));

        // Bulk-load existing rows once instead of one find() per incoming row.
        $existingById = $relation->whereIn($relation->getRelated()->getKeyName(), $incomingIds)
            ->get()
            ->keyBy($relation->getRelated()->getKeyName());

        // Delete rows that exist but were not present in the incoming payload.
        $relation->get()
            ->whereNotIn($relation->getRelated()->getKeyName(), $incomingIds)
            ->each(function (Model $orphan) {
                $this->deleteRowWithFiles($orphan);
            });

        foreach ($incomingRows as $row) {
            $id = $row['id'] ?? null;
            unset($row['id']);

            if ($id !== null && $existingById->has($id)) {
                /** @var Model $childModel */
                $childModel = $existingById->get($id);
                $childModel->fill($row);
                $childModel->save();
            } else {
                /** @var Model $childModel */
                $childModel = $relation->create($row);
            }

            $this->handleChildFiles($childModel, $row);
            $this->handleChildRecursion($childModel, $row, $context->depth);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeIncomingRows(mixed $incomingData): array
    {
        if (! is_array($incomingData) || $incomingData === []) {
            return [];
        }

        // Guard against a single associative row being passed instead of a list.
        if (array_is_list($incomingData)) {
            return $incomingData;
        }

        return [$incomingData];
    }

    private function handleChildFiles(Model $childModel, array $row): void
    {
        if (! $this->capabilities->supportsFileUpload($childModel)) {
            return;
        }

        foreach ($childModel->requestKeysForFile() as $fileAttribute) {
            if (array_key_exists($fileAttribute, $row)) {
                $this->files->applyIncomingValue($childModel, $fileAttribute, $row[$fileAttribute]);
            }
        }
    }

    private function handleChildRecursion(Model $childModel, array $row, int $parentDepth): void
    {
        $hasNestedCapability = $this->capabilities->supportsHasMany($childModel)
            || $this->capabilities->supportsHasOne($childModel)
            || $this->capabilities->supportsManyToMany($childModel);

        if (! $hasNestedCapability) {
            return;
        }

        $this->container->make(RelationSyncManagerInterface::class)->syncAll($childModel, $row, $parentDepth + 1);
    }

    private function deleteRowWithFiles(Model $orphan): void
    {
        if ($this->capabilities->supportsFileUpload($orphan)) {
            foreach ($orphan->requestKeysForFile() as $fileAttribute) {
                $this->files->delete($orphan, $fileAttribute);
            }
        }

        $orphan->delete();
    }
}
