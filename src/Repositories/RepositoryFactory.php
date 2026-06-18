<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Repositories;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Repositories\RepositoryInterface;

/**
 * A generic package cannot know consuming applications' model classes
 * ahead of time, so repositories can't be bound to the container directly
 * per model. This factory defers that decision to call time instead.
 *
 * Bound as a singleton in the service provider; {@see make()} itself is
 * cheap and stateless, so sharing the factory instance is safe even
 * though each repository it builds is request-scoped.
 */
final class RepositoryFactory
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function make(string $modelClass): RepositoryInterface
    {
        return new EloquentRepository($modelClass);
    }
}
