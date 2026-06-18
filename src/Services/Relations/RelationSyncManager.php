<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Relations;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncStrategyInterface;
use Nexus\CrudEngine\DTOs\Enums\RelationType;
use Nexus\CrudEngine\DTOs\RelationSyncContext;
use Nexus\CrudEngine\Events\RelationSynced;
use Nexus\CrudEngine\Exceptions\UnsupportedCapabilityException;

/**
 * Replaces `App\Core\Traits\RelationsHandleForCrud` and the three static
 * `HandleRelation*` classes.
 *
 * Asks the {@see CapabilityRegistryInterface} which relation capabilities
 * a model declares, then for each declared relation name present in the
 * incoming data, dispatches to the matching {@see RelationSyncStrategyInterface}.
 * This is the one place recursion depth is threaded through — strategies
 * never decide on their own which capability check to use for a child
 * model, eliminating the class of bug that caused the original HasOne/
 * HasMany mix-up.
 */
final class RelationSyncManager implements RelationSyncManagerInterface
{
    /**
     * @param array<string, RelationSyncStrategyInterface> $strategies Keyed by RelationType::value.
     */
    public function __construct(
        private readonly CapabilityRegistryInterface $capabilities,
        private readonly array $strategies,
        private readonly bool $strictCapabilities,
        private readonly Dispatcher $events,
    ) {
    }

    public function syncAll(Model $model, array $data, int $depth = 0): void
    {
        if ($this->capabilities->supportsHasMany($model)) {
            $this->dispatchEach($model, $data, $model->getHasManyRelations(), RelationType::HasMany, $depth);
        }

        if ($this->capabilities->supportsHasOne($model)) {
            $this->dispatchEach($model, $data, $model->getHasOneRelations(), RelationType::HasOne, $depth);
        }

        if ($this->capabilities->supportsManyToMany($model)) {
            $this->dispatchEach($model, $data, $model->getManyToManyRelations(), RelationType::ManyToMany, $depth);
        }
    }

    /**
     * @param string[] $relationNames
     */
    private function dispatchEach(Model $model, array $data, array $relationNames, RelationType $type, int $depth): void
    {
        foreach ($relationNames as $relationName) {
            if (! array_key_exists($relationName, $data)) {
                if ($this->strictCapabilities) {
                    throw UnsupportedCapabilityException::forRelation($relationName, $model::class);
                }

                continue;
            }

            $strategy = $this->strategies[$type->value]
                ?? throw new \LogicException("No relation sync strategy registered for [{$type->value}].");

            $strategy->sync(new RelationSyncContext($model, $relationName, $data[$relationName], $type, $depth));

            $this->events->dispatch(new RelationSynced($model, $relationName, $type));
        }
    }
}
