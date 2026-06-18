<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Capabilities;

/**
 * Marks an Eloquent model as owning one or more "has one" relations that
 * should be updated-or-created automatically when this model is
 * created/updated through the package's Crud services.
 */
interface HasOneRelations
{
    /**
     * Relation method names (as defined on the model) that should be
     * synced automatically, e.g. ['profile', 'settings'].
     *
     * @return string[]
     */
    public function getHasOneRelations(): array;
}
