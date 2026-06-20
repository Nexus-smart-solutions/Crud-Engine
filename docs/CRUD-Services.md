# CRUD Services

The four abstract Crud service classes are the primary integration point for consuming applications. Each is a Template Method — subclasses define what model and request class to use; the base class orchestrates the full operation pipeline.

---

## `AbstractStoreService`

**Namespace:** `Nexus\CrudEngine\Services\Crud\AbstractStoreService`

**Implements:** `Nexus\CrudEngine\Contracts\Services\CreatesRecords`

**Purpose:** Orchestrates the full create pipeline: validate → strip file fields → begin transaction → persist → commit → handle files → sync relations → dispatch `RecordCreated` → return `CrudOperationResult`.

### Constructor Dependencies (injected by the container)

| Dependency | Interface | Notes |
|---|---|---|
| `RequestValidatorInterface` | — | Validates current request and returns only validated fields |
| `RepositoryFactory` | — | Creates a repository bound to `model()` at call time |
| `FileLifecycleServiceInterface` | — | Stores/deletes file attributes after the transaction |
| `RelationSyncManagerInterface` | — | Syncs all declared relations after the transaction |
| `CapabilityRegistryInterface` | — | Detects whether the model supports file upload |
| `ResponseFormatterInterface` | — | Formats the result (unused directly — available for override) |
| `Dispatcher` | — | Dispatches `RecordCreated` |
| `ConnectionResolverInterface` | — | Provides the DB connection for transaction wrapping |

### Abstract Methods (you must implement)

```php
abstract public function model(): string;        // returns class-string<Model>
abstract public function requestFile(): string;  // returns class-string with rules(): array
```

### Overridable Methods (optional)

```php
public function successMessage(): string  // default: 'crud-engine::responses.success.created'
public function errorMessage(): string    // default: 'crud-engine::responses.error.create_failed'
protected function beforePersist(array $data): array  // hook to mutate validated data before save
```

### `store()` Return Value

`CrudOperationResult::success()` with `code: 201` and `data: $model->toArray()`.

### Execution order

1. `validate($this->requestFile())` → throws `CrudValidationException` on failure
2. `beforePersist($data)` — override hook
3. `withoutFileFields($data, $this->model())` — removes file attributes from the mass-assignment array
4. `DB::transaction(fn() => $repository->create($data))` — persists the model
5. `handleFiles($model, $data)` — stores any `UploadedFile` instances **after** the transaction
6. `relations->syncAll($model, $data)` — syncs all declared relations
7. `events->dispatch(new RecordCreated($model, $context))`
8. Returns `CrudOperationResult::success(data: $model->toArray(), code: 201)`

### Minimal subclass

```php
use Nexus\CrudEngine\Services\Crud\AbstractStoreService;

class PostStoreService extends AbstractStoreService
{
    public function model(): string
    {
        return Post::class;
    }

    public function requestFile(): string
    {
        return PostStoreRequest::class;
    }
}
```

### With `beforePersist` hook

```php
class PostStoreService extends AbstractStoreService
{
    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostStoreRequest::class; }

    protected function beforePersist(array $data): array
    {
        $data['user_id'] = auth()->id();
        $data['slug'] = Str::slug($data['title']);
        return $data;
    }
}
```

### Controller usage

```php
class PostController extends Controller
{
    public function store(PostStoreService $service): JsonResponse
    {
        $result = $service->store();
        return response()->json($result->toArray(), $result->code);
    }
}
```

---

## `AbstractUpdateService`

**Namespace:** `Nexus\CrudEngine\Services\Crud\AbstractUpdateService`

**Implements:** `Nexus\CrudEngine\Contracts\Services\UpdatesRecords`

**Purpose:** Orchestrates the full update pipeline — same structure as `AbstractStoreService` but requires a resolved model instance.

### Constructor Dependencies

Same eight dependencies as `AbstractStoreService`.

### Abstract Methods

```php
abstract public function model(): string;
abstract public function requestFile(): string;
abstract public function resolveModel(): Model;  // returns the model instance to update
```

### Overridable Methods

```php
public function successMessage(): string  // default: 'crud-engine::responses.success.updated'
public function errorMessage(): string    // default: 'crud-engine::responses.error.update_failed'
protected function beforePersist(array $data, Model $model): array
```

### `update()` Return Value

`CrudOperationResult::success()` with `code: 200` and `data: $model->refresh()->toArray()`. Note: `refresh()` is called to pick up any attribute changes made by file handling after the transaction.

### Recommended subclass pattern

```php
class PostUpdateService extends AbstractUpdateService
{
    private ?Post $post = null;

    public function forPost(Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostUpdateRequest::class; }

    public function resolveModel(): Model
    {
        return $this->post ?? throw new \LogicException('Call forPost() before update().');
    }
}
```

### Controller usage

```php
class PostController extends Controller
{
    public function update(Post $post, PostUpdateService $service): JsonResponse
    {
        $result = $service->forPost($post)->update();
        return response()->json($result->toArray(), $result->code);
    }
}
```

---

## `AbstractDeleteService`

**Namespace:** `Nexus\CrudEngine\Services\Crud\AbstractDeleteService`

**Implements:** `Nexus\CrudEngine\Contracts\Services\DeletesRecords`

**Purpose:** Deletes one or more model instances, handling file cleanup and dispatching events per record. Returns partial-success results for bulk operations where some records fail.

### Constructor Dependencies

| Dependency | Notes |
|---|---|
| `FileLifecycleServiceInterface` | Deletes file attributes before deleting the model |
| `CapabilityRegistryInterface` | Checks if model supports file upload |
| `Dispatcher` | Dispatches `RecordDeleted` or `RecordDeletionFailed` per record |

### Abstract Methods

```php
abstract public function model(): string;
abstract public function resolveTargets(): Collection;  // Collection of Model instances to delete
```

### Overridable Methods

```php
public function successMessage(): string        // default: 'crud-engine::responses.success.deleted'
public function partialSuccessMessage(): string // default: 'crud-engine::responses.error.partial_delete'
public function errorMessage(): string          // default: 'crud-engine::responses.error.delete_failed'
```

### `delete()` Return Values

| Scenario | Result | HTTP Code |
|---|---|---|
| `resolveTargets()` returns empty collection | `CrudOperationResult::error(...)` | 404 |
| All targets deleted successfully | `CrudOperationResult::success(...)` | 200 |
| Some targets deleted, some failed | `CrudOperationResult::partialSuccess(...)` | 207 |
| All targets failed | `CrudOperationResult::error(...)` | 500 |

### Subclass example

```php
class PostDeleteService extends AbstractDeleteService
{
    private ?Collection $posts = null;

    public function forPosts(Collection $posts): static
    {
        $this->posts = $posts;
        return $this;
    }

    public function model(): string { return Post::class; }

    public function resolveTargets(): Collection
    {
        return $this->posts ?? new Collection();
    }
}
```

### Controller usage

```php
class PostController extends Controller
{
    public function destroy(Post $post, PostDeleteService $service): JsonResponse
    {
        $result = $service->forPosts(new Collection([$post]))->delete();
        return response()->json($result->toArray(), $result->code);
    }
}
```

---

## `AbstractBulkDeleteService`

**Namespace:** `Nexus\CrudEngine\Services\Crud\AbstractBulkDeleteService`

**Extends:** `AbstractDeleteService`

**Purpose:** Specialization that reads target IDs from `request()->input('ids')` and bulk-loads the matching records. Implements `resolveTargets()` — you only need to define `model()`.

### Additional Constructor Dependencies (beyond `AbstractDeleteService`)

| Dependency | Notes |
|---|---|
| `RepositoryFactory` | Loads records matching the IDs |
| `Request` | Injected (not `request()` helper) — reads the `ids` input |

### Overridable Methods

```php
protected function resolveIds(): array  // default: reads 'ids' from injected Request
```

Override `resolveIds()` to source IDs from somewhere other than the request body (e.g. route parameters, a DTO, or a queue job payload).

### ID normalization (Bug 4.5 fix)

The original codebase called `array_filter($ids, 'is_numeric')` directly on `request()->input('ids') ?? []` with no check that the value was actually an array. A scalar `ids` value (e.g. `?ids=5`) threw a `TypeError`. The fixed `resolveIds()` wraps a non-array value in a single-element array before filtering:

```php
protected function resolveIds(): array
{
    $rawIds = $this->request->input('ids', []);
    if (! is_array($rawIds)) {
        $rawIds = [$rawIds];
    }
    return array_values(array_filter($rawIds, static fn ($id) => is_numeric($id)));
}
```

### Minimal subclass

```php
class PostBulkDeleteService extends AbstractBulkDeleteService
{
    public function model(): string
    {
        return Post::class;
    }
}
```

### Controller usage

```php
class PostController extends Controller
{
    // Request body: { "ids": [1, 2, 3] }
    public function bulkDestroy(PostBulkDeleteService $service): JsonResponse
    {
        $result = $service->delete();
        return response()->json($result->toArray(), $result->code);
    }
}
```

---

## Common Mistakes Across All Crud Services

**1. Calling `store()`/`update()` from a queued job without the HTTP lifecycle**

The `RequestValidatorInterface` depends on the current `Request`, which is populated during an HTTP request. Calling a Crud service from a queue worker without simulating a request will result in an empty `$request->all()` and validation will fail silently or unexpectedly. Use a DTO-based approach for queue-driven CRUD instead.

**2. Defining `requestFile()` with a class that doesn't have `rules()`**

`LaravelRequestValidator` checks `method_exists($requestClass, 'rules')` and throws `\InvalidArgumentException` if it's missing. Any POPO (plain PHP object) with a public `rules(): array` method works — it does not need to extend `FormRequest`.

**3. Not returning the model from `resolveModel()` in UpdateService**

If `resolveModel()` returns a fresh model from the database instead of the model already bound by route-model binding, Eloquent will fetch it twice. Prefer passing the already-resolved model via a setter (`forPost($post)`) rather than refetching by ID.

**4. Expecting `store()` to return HTTP 200**

Store returns `201 Created`. Use `$result->code`, not a hardcoded `200`.
