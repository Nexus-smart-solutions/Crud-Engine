<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence abstraction for a single Eloquent model class.
 *
 * Crud services depend on this contract rather than calling Eloquent
 * statics directly, which makes them testable against an in-memory fake
 * without touching a real database.
 */
interface RepositoryInterface
{
    /**
     * @return class-string<Model>
     */
    public function modelClass(): string;

    public function create(array $attributes): Model;

    public function update(Model $model, array $attributes): Model;

    public function delete(Model $model): bool;

    public function find(int|string $id): ?Model;

    /**
     * @param array<int, int|string> $ids
     */
    public function findManyByIds(array $ids): Collection;
}
