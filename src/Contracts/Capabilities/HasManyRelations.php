<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Capabilities;

/**
 * Marks an Eloquent model as owning one or more "has many" relations that
 * should be diff-synced automatically when this model is created/updated
 * through the package's Crud services.
 */
interface HasManyRelations
{
    /**
     * Relation method names (as defined on the model) that should be
     * synced automatically, e.g. ['items', 'attachments'].
     *
     * @return string[]
     */
    public function getHasManyRelations(): array;
}
