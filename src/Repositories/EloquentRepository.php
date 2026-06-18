<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Repositories\RepositoryInterface;

/**
 * Generic Eloquent-backed repository, bound to a single model class at
 * construction time. Isolates persistence from the Crud services so they
 * can be unit-tested against an in-memory fake instead of a real
 * database.
 */
final class EloquentRepository implements RepositoryInterface
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(private readonly string $modelClass)
    {
    }

    public function modelClass(): string
    {
        return $this->modelClass;
    }

    public function create(array $attributes): Model
    {
        return $this->newQuery()->create($attributes);
    }

    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function find(int|string $id): ?Model
    {
        return $this->newQuery()->find($id);
    }

    public function findManyByIds(array $ids): Collection
    {
        return $this->newQuery()->whereIn($this->newQuery()->getModel()->getKeyName(), $ids)->get();
    }

    private function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var Model $instance */
        $instance = new $this->modelClass();

        return $instance->query();
    }
}
