<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Capabilities;

/**
 * Marks an Eloquent model as owning one or more many-to-many relations
 * that should be synced (via Eloquent's `sync()`) automatically when this
 * model is created/updated through the package's Crud services.
 */
interface ManyToManyRelations
{
    /**
     * Relation method names (as defined on the model) that should be
     * synced automatically, e.g. ['tags', 'categories'].
     *
     * @return string[]
     */
    public function getManyToManyRelations(): array;
}
