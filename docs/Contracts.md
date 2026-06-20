# Contracts

All contracts live under `Nexus\CrudEngine\Contracts\`. They are the package's public extension points — every class inside the package depends on these interfaces rather than concrete implementations, and every interface can be rebound in your application's service provider.

---

## Capability Contracts

### `Capabilities\FileUpload`

**Namespace:** `Nexus\CrudEngine\Contracts\Capabilities\FileUpload`

**Purpose:** Marks an Eloquent model as owning one or more file-backed attributes. Implementing this interface is how a model opts in to automatic file storage, deletion, and URL rewriting by `FileLifecycleService` and `HasFileUrlsTrait`.

**Public API:**

```php
public function documentFullPathStore(): string;
public function requestKeysForFile(): array;
```

| Method | Return | Description |
|---|---|---|
| `documentFullPathStore()` | `string` | Storage directory relative to the configured disk root, e.g. `"posts/42"`. The package never assumes a fixed structure — this method is the single source of truth for where a model's files live. |
| `requestKeysForFile()` | `string[]` | Attribute names on the model that are file-backed, e.g. `['cover_image', 'thumbnail']`. |

**Usage example:**

```php
class Post extends Model implements FileUpload
{
    public function documentFullPathStore(): string
    {
        return 'posts/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image'];
    }
}
```

**Common mistakes:**
- Returning a path with leading/trailing slashes from `documentFullPathStore()`. The `PathHelper::joinPath()` method handles normalization, so `posts/42`, `/posts/42/`, and `posts/42` all work, but be consistent.
- Listing an attribute in `requestKeysForFile()` that the database column does not allow null on. When `delete()` is called, the column is set to `null` — ensure it is nullable in the migration.

---

### `Capabilities\HasManyRelations`

**Purpose:** Marks a model as owning one or more `hasMany` relations that should be diff-synced automatically during store/update operations.

**Public API:**

```php
public function getHasManyRelations(): array;
```

| Method | Return | Description |
|---|---|---|
| `getHasManyRelations()` | `string[]` | Names of relation methods on the model, e.g. `['comments', 'variants']`. |

**Usage example:**

```php
class Post extends Model implements HasManyRelations
{
    public function getHasManyRelations(): array
    {
        return ['comments'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

**Behavior:** `HasManySyncStrategy` performs a diff: incoming rows with an `id` are updated, rows without an `id` are created, and existing rows whose `id` is absent from the payload are deleted.

---

### `Capabilities\HasOneRelations`

**Purpose:** Marks a model as owning one or more `hasOne` relations that should be update-or-created automatically during store/update operations.

**Public API:**

```php
public function getHasOneRelations(): array;
```

| Method | Return | Description |
|---|---|---|
| `getHasOneRelations()` | `string[]` | Names of relation methods on the model, e.g. `['profile', 'settings']`. |

**Usage example:**

```php
class Post extends Model implements HasOneRelations
{
    public function getHasOneRelations(): array
    {
        return ['meta'];
    }

    public function meta(): HasOne
    {
        return $this->hasOne(PostMeta::class);
    }
}
```

---

### `Capabilities\ManyToManyRelations`

**Purpose:** Marks a model as owning one or more `belongsToMany` relations that should be synced via Eloquent's `sync()` during store/update operations.

**Public API:**

```php
public function getManyToManyRelations(): array;
```

| Method | Return | Description |
|---|---|---|
| `getManyToManyRelations()` | `string[]` | Names of relation methods, e.g. `['tags', 'categories']`. |

**Usage example:**

```php
class Post extends Model implements ManyToManyRelations
{
    public function getManyToManyRelations(): array
    {
        return ['tags'];
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

**Behavior:** `ManyToManySyncStrategy` calls `$model->tags()->sync($ids)`. The incoming payload for a many-to-many relation must be an array of IDs, not an array of row objects.

---

### `Capabilities\OriginalName`

**Purpose:** Marker interface. When a model implements this, `FileLifecycleService` selects `OriginalFilenameStrategy` instead of the default `HashedFilenameStrategy`. No methods — presence of the interface is the signal.

**Usage example:**

```php
class LegalDocument extends Model implements FileUpload, OriginalName
{
    // Files are stored under the sanitized original client filename.
}
```

**Best practice:** Only use `OriginalName` when consumers specifically need to recover the original filename from the storage path. Be aware that two uploads sharing the same sanitized filename for the same model will overwrite each other — the `HashedFilenameStrategy` avoids this by design.

---

## `CapabilityRegistryInterface`

**Namespace:** `Nexus\CrudEngine\Contracts\CapabilityRegistryInterface`

**Purpose:** Single source of truth for "what can this model do?" Every Crud service, relation strategy, and the `HasFileUrlsTrait` asks this registry instead of performing its own `instanceof` check. The default implementation is `CapabilityRegistry`, a thin wrapper around `instanceof` against the five capability interfaces.

**Binding:** `singleton` in the service provider.

**Public API:**

```php
public function supportsFileUpload(object $model): bool;
public function supportsHasMany(object $model): bool;
public function supportsHasOne(object $model): bool;
public function supportsManyToMany(object $model): bool;
public function usesOriginalFilename(object $model): bool;
```

**Custom implementation example:**

```php
// Attribute-based discovery instead of interface checks:
class AttributeCapabilityRegistry implements CapabilityRegistryInterface
{
    public function supportsFileUpload(object $model): bool
    {
        return isset($model->fileAttributes) && count($model->fileAttributes) > 0;
    }
    // ...
}

// In AppServiceProvider:
$this->app->singleton(CapabilityRegistryInterface::class, AttributeCapabilityRegistry::class);
```

---

## `Repositories\RepositoryInterface`

**Purpose:** Persistence abstraction for a single Eloquent model class. Crud services depend on this rather than Eloquent statics directly, enabling unit tests with in-memory fakes.

**Public API:**

```php
public function modelClass(): string;
public function create(array $attributes): Model;
public function update(Model $model, array $attributes): Model;
public function delete(Model $model): bool;
public function find(int|string $id): ?Model;
public function findManyByIds(array $ids): Collection;
```

Repositories are not bound directly to the container per model — they are created at call time by `RepositoryFactory::make(string $modelClass)`.

---

## `Files\FileLifecycleServiceInterface`

**Purpose:** Owns the full lifecycle of a model's file-backed attributes. Replaces the original static `StoragePictures` class.

**Binding:** `bind` (not singleton) — request/tenant-sensitive.

**Public API:**

```php
public function store(Model $model, string $attribute, UploadedFile $file): FileOperation;
public function delete(Model $model, string $attribute): FileOperation;
public function url(Model $model, string $fileName): string;
public function applyIncomingValue(Model $model, string $attribute, mixed $incomingValue): ?FileOperation;
```

See [FileUploads.md](FileUploads.md) for detailed usage.

---

## `Files\FileNamingStrategyInterface`

**Purpose:** Decides what filename an uploaded file is stored under.

**Public API:**

```php
public function generateName(UploadedFile $file, Model $model): string;
```

Two implementations ship: `HashedFilenameStrategy` (default) and `OriginalFilenameStrategy` (selected when the model implements `OriginalName`). Custom implementations can be provided by binding the interface in your service provider.

---

## `Files\FilePathResolverInterface`

**Purpose:** Resolves the storage directory for a given model's files. Default implementation calls `$model->documentFullPathStore()`. Override to centralize path logic across all models.

**Binding:** `bind` in the service provider.

**Public API:**

```php
public function resolve(Model $model): string;
```

---

## `Relations\RelationSyncManagerInterface`

**Purpose:** Orchestrates relation syncing for a model — inspects capabilities via `CapabilityRegistryInterface` and dispatches each declared relation to the matching `RelationSyncStrategyInterface`.

**Binding:** `singleton`.

**Public API:**

```php
public function syncAll(Model $model, array $data, int $depth = 0): void;
```

| Parameter | Type | Description |
|---|---|---|
| `$model` | `Model` | The parent model whose relations should be synced. |
| `$data` | `array` | The validated incoming data array (same array passed to the Crud service). |
| `$depth` | `int` | Current recursion depth. Pass `0` for top-level calls; strategies increment it. |

---

## `Relations\RelationSyncStrategyInterface`

**Purpose:** One strategy per relation type. Each implementation owns exactly one sync algorithm.

**Public API:**

```php
public function sync(RelationSyncContext $context): void;
```

---

## `Responses\ResponseFormatterInterface`

**Purpose:** Builds the outward-facing JSON response envelope. Single source of truth for translation-key resolution (consolidates four previously duplicated copies).

**Binding:** `bind`, class resolved from `crud-engine.response_formatter` config key.

**Public API:**

```php
public function format(CrudOperationResult $result): JsonResponse;
public function translate(string $message): string;
```

`translate()` resolves a dotted string as a Laravel translation key if it contains a dot; returns the string unchanged otherwise.

---

## `Validation\RequestValidatorInterface`

**Purpose:** Validates the current request against a request class's `rules()` and returns only the validated fields. Replaces `DataArrayFromRequestTrait` and fixes the mass-assignment exposure of the original code.

**Binding:** `bind`.

**Public API:**

```php
public function validate(string $requestClass): array;
```

Throws `CrudValidationException` on failure.

---

## `Statistics\StatisticsQueryStrategyInterface`

**Purpose:** Executes a time-bucketed aggregate query and returns raw `{date_group => value}` rows. Bound to `EloquentAggregateStrategy` by default, or `SpatieQueryBuilderStrategy` when configured.

**Public API:**

```php
public function execute(StatisticsQuery $query): array;
```

Returns `array<string, float|int>` — keys are date bucket strings (`'2026-01-15'`, `'2026-01'`, `'2026'`), values are aggregate counts or sums. Empty buckets are NOT included — `AbstractStatisticsService` fills them.

---

## `Services\CreatesRecords`, `UpdatesRecords`, `DeletesRecords`

**Purpose:** Public-facing contracts for the three Crud operation types. Controllers type-hint these instead of concrete abstract classes so implementations can be swapped or faked in tests.

```php
interface CreatesRecords { public function store(): CrudOperationResult; }
interface UpdatesRecords { public function update(): CrudOperationResult; }
interface DeletesRecords { public function delete(): CrudOperationResult; }
```
