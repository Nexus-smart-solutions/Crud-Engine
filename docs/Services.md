# Services

This document covers all concrete service implementations that are not the four abstract Crud services (which are documented in [CRUD-Services.md](CRUD-Services.md)).

---

## `CapabilityRegistry`

**Namespace:** `Nexus\CrudEngine\Services\Capabilities\CapabilityRegistry`

**Implements:** `CapabilityRegistryInterface`

**Binding:** `singleton`

**Purpose:** The single place that decides "does this model support X." Every class that previously performed its own `instanceof` check now asks this registry instead. This centralization was the direct fix for the original Bug 4.3, where `HandleRelationHasOne` called `getHasManyRelations()` by mistake because the capability check was duplicated independently in six files.

**Constructor:** No dependencies.

**All methods are pure `instanceof` checks:**

```php
public function supportsFileUpload(object $model): bool  // $model instanceof FileUpload
public function supportsHasMany(object $model): bool     // $model instanceof HasManyRelations
public function supportsHasOne(object $model): bool      // $model instanceof HasOneRelations
public function supportsManyToMany(object $model): bool  // $model instanceof ManyToManyRelations
public function usesOriginalFilename(object $model): bool // $model instanceof OriginalName
```

**Best practice:** Never call `instanceof` against the five capability interfaces outside of this class. Always inject and call `CapabilityRegistryInterface` — this is how the package's own code works.

---

## `FileLifecycleService`

**Namespace:** `Nexus\CrudEngine\Services\Files\FileLifecycleService`

**Implements:** `FileLifecycleServiceInterface`

**Binding:** `bind` (not singleton — disk and path resolution may be tenant/request-sensitive)

**Purpose:** Replaces the original static `StoragePictures` class. Owns the full lifecycle of file-backed model attributes.

**Constructor:**

```php
public function __construct(
    FilesystemFactory $filesystem,            // Illuminate\Contracts\Filesystem\Factory
    CapabilityRegistryInterface $capabilities, // selects naming strategy
    FilePathResolverInterface $pathResolver,  // resolves storage directory
    FileNamingStrategyInterface $hashedNamingStrategy,
    FileNamingStrategyInterface $originalNamingStrategy,
    Dispatcher $events,
    string $disk,                            // from crud-engine.files.disk config
)
```

**Key behaviors:**

| Method | Behavior |
|---|---|
| `store()` | Selects naming strategy based on `usesOriginalFilename()`, writes via `$disk->putFileAs()`, sets and saves the model attribute, dispatches `FileStored` |
| `delete()` | Reads current filename from model attribute, deletes from disk, **sets attribute to `null` and saves** (Bug 4.2 fix), dispatches `FileDeleted` |
| `url()` | Calls `$disk->url(PathHelper::joinPath($directory, $fileName))` |
| `applyIncomingValue()` | Dispatches to `store()` for `UploadedFile`, `delete()` for `null`, returns `null` for anything else (no-op) |

**`applyIncomingValue()` decision table:**

| `$incomingValue` type | Action | Returns |
|---|---|---|
| `UploadedFile` | `store()` | `FileOperation(Stored, ...)` |
| `null` | `delete()` | `FileOperation(Deleted, ...)` |
| Any other (string, int, bool) | no-op | `null` |

---

## `ModelDefinedPathResolver`

**Namespace:** `Nexus\CrudEngine\Services\Files\ModelDefinedPathResolver`

**Implements:** `FilePathResolverInterface`

**Binding:** `bind`

**Purpose:** Default path resolver — delegates to `$model->documentFullPathStore()`. Throws `FileOperationException` if the model does not implement `FileUpload`.

**Constructor:** No dependencies.

**When to replace:** When you want to centralize path logic instead of defining `documentFullPathStore()` on every model. Example — tenant-aware paths:

```php
class TenantAwarePathResolver implements FilePathResolverInterface
{
    public function resolve(Model $model): string
    {
        $tenant = tenant()->id;
        $table  = $model->getTable();
        $id     = $model->getKey();
        return "{$tenant}/{$table}/{$id}";
    }
}

// In AppServiceProvider:
$this->app->bind(FilePathResolverInterface::class, TenantAwarePathResolver::class);
```

---

## `RelationSyncManager`

**Namespace:** `Nexus\CrudEngine\Services\Relations\RelationSyncManager`

**Implements:** `RelationSyncManagerInterface`

**Binding:** `singleton`

**Purpose:** Orchestrates relation syncing. Inspects all three relation capability types on a model and dispatches each declared relation name (that has a matching key in `$data`) to the appropriate strategy.

**Constructor:**

```php
public function __construct(
    CapabilityRegistryInterface $capabilities,
    array $strategies,             // [RelationType::value => RelationSyncStrategyInterface]
    bool $strictCapabilities,      // from crud-engine.strict_capabilities config
    Dispatcher $events,
)
```

**`syncAll()` execution:**

1. If `supportsHasMany($model)`: iterates `$model->getHasManyRelations()`, dispatches each present key to `HasManySyncStrategy`
2. If `supportsHasOne($model)`: iterates `$model->getHasOneRelations()`, dispatches each to `HasOneSyncStrategy`
3. If `supportsManyToMany($model)`: iterates `$model->getManyToManyRelations()`, dispatches each to `ManyToManySyncStrategy`
4. After each successful sync, dispatches `RelationSynced` event

When `strict_capabilities = true` and a declared relation name is absent from `$data`, throws `UnsupportedCapabilityException`. When `false` (default), silently skips.

---

## `LaravelRequestValidator`

**Namespace:** `Nexus\CrudEngine\Services\Validation\LaravelRequestValidator`

**Implements:** `RequestValidatorInterface`

**Binding:** `bind`

**Purpose:** Replaces `DataArrayFromRequestTrait`. Validates the current request and returns **only** `$validator->validated()`, never `$request->all()` (Bug 4.1 fix).

**Constructor:**

```php
public function __construct(
    ValidationFactory $validationFactory,  // Illuminate\Contracts\Validation\Factory
    Request $request,                      // Illuminate\Http\Request
    Container $container,                  // Illuminate\Contracts\Container\Container
)
```

**`validate()` execution:**

1. Checks `method_exists($requestClass, 'rules')` → throws `\InvalidArgumentException` if missing
2. Resolves the request class via `$container->make($requestClass)` (not `new`) so FormRequest constructor dependencies and route bindings work
3. Runs `$validationFactory->make($request->all(), $instance->rules())`
4. Throws `CrudValidationException` if validation fails
5. Returns `$validator->validated()` — only declared fields

**Common mistake:** Passing a class that has an `authorize()` method that returns `false`. `LaravelRequestValidator` only calls `rules()` — authorization is not evaluated here. Use route middleware for authorization.

---

## `JsonResponseFormatter`

**Namespace:** `Nexus\CrudEngine\Services\Responses\JsonResponseFormatter`

**Implements:** `ResponseFormatterInterface`

**Binding:** `bind` (class resolved from `crud-engine.response_formatter` config)

**Purpose:** Produces the `{status, messages, data, code}` JSON envelope. Single source of truth for translation-key resolution — consolidates four previously duplicated `translateMessage()` copies from the original codebase.

**Constructor:**

```php
public function __construct(Translator $translator)  // Illuminate\Contracts\Translation\Translator
```

**`format()` behavior:**

Calls `$result->toArray()`, then maps every message through `translate()`, and wraps in `JsonResponse` with `$result->code` as the HTTP status.

**`translate()` behavior:**

- If the string does not contain a dot → returned as-is
- If it contains a dot → passed to `$translator->get($message)`
- If translator returns the key itself (no translation found) → returns the raw string, not the key

This means a plain message like `"Record saved"` is returned unchanged, while `'crud-engine::responses.success.created'` is resolved to `"The record was created successfully."` (or the Arabic equivalent if the app locale is `ar`).

---

## `AbstractStatisticsService`

**Namespace:** `Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService`

**Purpose:** Replaces `AbstractStatisticsRowsCounted`. Provides cached, zero-filled time-bucketed statistics with a swappable query engine.

See [Statistics.md](Statistics.md) for full documentation.
