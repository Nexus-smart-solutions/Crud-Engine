# DTOs (Data Transfer Objects)

All DTOs are `final readonly` classes — plain data holders with no closures, no container references, and no open resources. They are safe to serialize into queue jobs.

---

## `CrudOperationResult`

**Namespace:** `Nexus\CrudEngine\DTOs\CrudOperationResult`

**Purpose:** Typed replacement for the raw `['status' => ..., 'messages' => ..., 'data' => ..., 'code' => ...]` arrays that every Crud operation returned in the original codebase.

**Constructor:**

```php
public function __construct(
    public OperationStatus $status,
    public array $messages = [],   // string[]
    public array $data = [],       // array<string, mixed>
    public int $code = 200,
    public array $failedIds = [],  // array<int, int|string>
    public array $meta = [],       // array<string, mixed>
)
```

**Static factories:**

```php
CrudOperationResult::success(array $data = [], array $messages = [], int $code = 200, array $meta = []): self
CrudOperationResult::partialSuccess(array $messages, array $failedIds, int $code = 207, array $meta = []): self
CrudOperationResult::error(array $messages, int $code = 500, array $meta = []): self
```

**Additional methods:**

```php
public function isSuccessful(): bool       // true when status === OperationStatus::Success
public function toArray(): array           // serializes to the JSON envelope shape
```

`toArray()` output:

```php
[
    'status'     => 'success',        // OperationStatus::value
    'messages'   => ['...'],
    'data'       => [...],
    'code'       => 201,
    // 'failed_ids' => [...] — only present when failedIds is non-empty
    // 'meta'       => [...] — only present when meta is non-empty
]
```

**Usage in a controller:**

```php
$result = $this->app->make(PostStoreService::class)->store();

if (! $result->isSuccessful()) {
    return response()->json($result->toArray(), $result->code);
}

return response()->json($result->toArray(), $result->code);
```

**Common mistake:** Checking `$result->code === 200` to determine success. Use `$result->isSuccessful()` — store operations return `201`, not `200`.

---

## `StoreContext`

**Purpose:** Typed replacement for the raw `array $data` passed through 4–5 layers of the original store pipeline. Carries the model class string and already-validated attributes.

**Constructor:**

```php
public function __construct(
    public string $modelClass,    // class-string<Model>
    public array $attributes,     // already-validated only
)
```

**Additional methods:**

```php
public function withAttributes(array $attributes): self
```

`withAttributes()` returns a new immutable instance with the given attributes, leaving the original unchanged. Used in `beforePersist()` hooks.

**Dispatched inside:** `RecordCreated` event payload.

---

## `UpdateContext`

**Purpose:** Typed pairing of the model being updated and its validated incoming attributes.

**Constructor:**

```php
public function __construct(
    public Model $model,
    public array $attributes,     // already-validated only
)
```

**Dispatched inside:** `RecordUpdated` event payload.

---

## `DeleteCriteria`

**Purpose:** Identifies which records a bulk-delete operation should target. Carries the model class and already-filtered numeric IDs.

**Constructor:**

```php
public function __construct(
    public string $modelClass,    // class-string<Model>
    public array $ids,            // array<int, int|string>
)
```

**Note:** `DeleteCriteria` is defined for structural completeness. `AbstractBulkDeleteService` resolves IDs from the request directly via `resolveIds()` — this DTO is not currently instantiated by the package itself, but is available for use in custom extensions.

---

## `FileOperation`

**Purpose:** Result of a single file lifecycle operation (store, delete, or skip) returned by `FileLifecycleServiceInterface`.

**Constructor:**

```php
public function __construct(
    public FileOperationType $type,     // Stored | Deleted | Skipped
    public string $attribute,           // e.g. 'cover_image'
    public ?string $fileName = null,    // stored or previously-stored filename
    public ?string $url = null,         // full URL (only set after store())
)
```

**Dispatched inside:** `FileStored` and `FileDeleted` event payloads.

**Usage example:**

```php
$operation = $files->store($post, 'cover_image', $request->file('cover_image'));

if ($operation->type === FileOperationType::Stored) {
    logger('Stored: '.$operation->fileName.' at '.$operation->url);
}
```

---

## `RelationSyncContext`

**Purpose:** Everything a `RelationSyncStrategyInterface` needs to sync one relation on one model. Passed by `RelationSyncManager` to each strategy's `sync()` method.

**Constructor:**

```php
public function __construct(
    public Model $model,
    public string $relationName,
    public mixed $incomingData,
    public RelationType $type,    // HasMany | HasOne | ManyToMany
    public int $depth = 0,
)
```

`$depth` is the current recursion level — strategies throw `RelationSyncException` if it exceeds `crud-engine.relations.max_recursion_depth`.

---

## `StatisticsQuery`

**Purpose:** Parameters for a single time-bucketed aggregate query, passed from `AbstractStatisticsService` to `StatisticsQueryStrategyInterface::execute()`.

**Constructor:**

```php
public function __construct(
    public string $modelClass,       // class-string<Model>
    public string $dateColumn,       // e.g. 'created_at'
    public ?string $sumColumn,       // null → count rows, string → sum this column
    public string $startDate,        // 'Y-m-d' or 'Y-m-d H:i:s'
    public string $endDate,
    public string $interval,         // 'days' | 'months' | 'years'
    public array $scopes = [],       // string[] — local scope method names
    public array $allowedFilters = [], // string[] — for Spatie strategy only
)
```

---

## Enums

### `Enums\OperationStatus`

```php
enum OperationStatus: string {
    case Success        = 'success';
    case PartialSuccess = 'partial_success';
    case Error          = 'error';
}
```

Used as `CrudOperationResult::$status`. `PartialSuccess` is returned by bulk-delete operations when at least one record succeeds and at least one fails.

### `Enums\FileOperationType`

```php
enum FileOperationType: string {
    case Stored  = 'stored';
    case Deleted = 'deleted';
    case Skipped = 'skipped';
}
```

`Skipped` is the value returned by `FileLifecycleServiceInterface::applyIncomingValue()` when the incoming value is neither an `UploadedFile` nor `null` (i.e. an unchanged existing filename string).

### `Enums\RelationType`

```php
enum RelationType: string {
    case HasMany    = 'has_many';
    case HasOne     = 'has_one';
    case ManyToMany = 'many_to_many';
}
```

Used as the key into the strategies array inside `RelationSyncManager`. Each `RelationType::value` maps to one `RelationSyncStrategyInterface` instance.
