# Repositories

---

## `EloquentRepository`

**Namespace:** `Nexus\CrudEngine\Repositories\EloquentRepository`

**Implements:** `Nexus\CrudEngine\Contracts\Repositories\RepositoryInterface`

**Purpose:** Generic Eloquent-backed persistence adapter bound to a single model class. Isolates database operations from the Crud services so they can be tested against an in-memory fake without a real database connection.

**Constructor:**

```php
public function __construct(string $modelClass)  // class-string<Model>
```

The `$modelClass` is stored and used to instantiate fresh query builders via `(new $modelClass)->query()`. It is never changed after construction.

**Public API:**

```php
public function modelClass(): string
public function create(array $attributes): Model
public function update(Model $model, array $attributes): Model
public function delete(Model $model): bool
public function find(int|string $id): ?Model
public function findManyByIds(array $ids): Collection
```

### `create(array $attributes): Model`

Calls `$this->newQuery()->create($attributes)`. The attributes are already validated and stripped of file fields before this is called — the Crud service handles that.

### `update(Model $model, array $attributes): Model`

Calls `$model->fill($attributes)` then `$model->save()`. Returns the same model instance with the new attribute values set and the model saved.

### `delete(Model $model): bool`

Calls `$model->delete()` and returns `(bool)` of the result. Soft-deletes work automatically when the model uses `SoftDeletes`.

### `find(int|string $id): ?Model`

Returns `null` when not found (no exception). The Crud service is responsible for handling the `null` case.

### `findManyByIds(array $ids): Collection`

Uses a single `whereIn($keyName, $ids)->get()` query — not one `find()` per ID. Called by `AbstractBulkDeleteService` to load all deletion targets in one query.

---

## `RepositoryFactory`

**Namespace:** `Nexus\CrudEngine\Repositories\RepositoryFactory`

**Binding:** `singleton` in the service provider.

**Purpose:** The package cannot bind a concrete repository per model class in advance because it has no knowledge of which models a consuming application defines. `RepositoryFactory` defers that decision to call time.

**Constructor:** No dependencies.

**Public API:**

```php
public function make(string $modelClass): RepositoryInterface
```

Each call to `make()` returns a new `EloquentRepository` instance bound to the given class. The factory itself is a singleton because `make()` is stateless and cheap — there is no reason to create multiple factory instances.

**Usage inside a Crud service:**

```php
// Inside AbstractStoreService::store():
$repository = $this->repositoryFactory->make($this->model());
$model = $connection->transaction(fn() => $repository->create($data));
```

---

## Replacing the Repository

Bind a custom implementation of `RepositoryInterface` by extending `RepositoryFactory`:

```php
class CachingRepositoryFactory extends RepositoryFactory
{
    public function make(string $modelClass): RepositoryInterface
    {
        return new CachingEloquentRepository($modelClass, app('cache'));
    }
}

// In AppServiceProvider:
$this->app->singleton(RepositoryFactory::class, CachingRepositoryFactory::class);
```

This pattern is preferred over binding `RepositoryInterface` directly because the interface is model-specific (each instance is bound to one model class) while the container binding is global.

---

## Testing with a Fake Repository

The entire point of the repository abstraction is to enable unit tests that don't hit the database. Create an in-memory implementation:

```php
class FakeRepository implements RepositoryInterface
{
    private array $store = [];
    private int $nextId = 1;

    public function __construct(private string $class) {}

    public function modelClass(): string { return $this->class; }

    public function create(array $attrs): Model
    {
        $model = new $this->class($attrs);
        $model->id = $this->nextId++;
        $this->store[$model->id] = $model;
        return $model;
    }

    public function update(Model $model, array $attrs): Model
    {
        $model->fill($attrs);
        $this->store[$model->getKey()] = $model;
        return $model;
    }

    public function delete(Model $model): bool
    {
        unset($this->store[$model->getKey()]);
        return true;
    }

    public function find(int|string $id): ?Model
    {
        return $this->store[$id] ?? null;
    }

    public function findManyByIds(array $ids): Collection
    {
        return collect(array_intersect_key($this->store, array_flip($ids)));
    }
}
```

Swap it in a test:

```php
$fakeFactory = new class extends RepositoryFactory {
    public function make(string $modelClass): RepositoryInterface
    {
        return new FakeRepository($modelClass);
    }
};

$this->app->instance(RepositoryFactory::class, $fakeFactory);
```
