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
 * Update-or-create sync for a "has one" relation, with file handling and
 * recursion into the related model's own nested relations.
 *
 * Replaces the original static `HandleRelationHasOne` class — and fixes
 * Bug 4.3 from the Phase 1 audit: the original code recursed into nested
 * relations by calling `$existingRecord->getHasManyRelations()` inside
 * the "has one" handler, a copy-paste mistake. Here, recursion is
 * delegated entirely to {@see RelationSyncManagerInterface::syncAll()},
 * which asks the {@see CapabilityRegistryInterface} which capabilities
 * the related model actually has — there is no longer any hardcoded
 * "HasMany vs HasOne" branch to get backwards.
 *
 * See {@see HasManySyncStrategy}'s class doc for why `Container` is
 * injected instead of `RelationSyncManagerInterface` directly: the two
 * services have a genuine circular dependency, and resolving the
 * manager lazily at point of use breaks it without a static call.
 */
final class HasOneSyncStrategy implements RelationSyncStrategyInterface
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

        $row = is_array($context->incomingData) ? $context->incomingData : [];

        if ($row === []) {
            return;
        }

        unset($row['id']);

        /** @var \Illuminate\Database\Eloquent\Relations\HasOne $relation */
        $relation = $model->{$relationName}();

        $existing = $relation->first();

        if ($existing !== null) {
            $existing->fill($row);
            $existing->save();
            $childModel = $existing;
        } else {
            $childModel = $relation->create($row);
        }

        $this->handleChildFiles($childModel, $row);
        $this->handleChildRecursion($childModel, $row, $context->depth);
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
}
