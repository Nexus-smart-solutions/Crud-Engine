# Configuration

Published to `config/crud-engine.php` via `php artisan crud-engine:install`.

---

## `strict_validation`

**Type:** `bool` **Default:** `true`

```php
'strict_validation' => true,
```

Controls what `LaravelRequestValidator::validate()` returns after a request passes validation.

- **`true` (default):** Returns only `$validator->validated()` — the fields explicitly declared in the request class's `rules()`. Fields the client sends that are not declared in `rules()` are silently discarded before they reach `Model::create()` or `Model::update()`. This is the correct, safe behavior and fixes the mass-assignment exposure that existed in the original `DataArrayFromRequestTrait`.
- **`false`:** Not supported in the current implementation. The fix is unconditional — `validated()` is always returned. This config key is reserved for documentation clarity and future backward-compatibility shim.

**Common mistake:** Assuming `strict_validation` can be disabled to allow extra fields through. It cannot. If a field is needed in `create()`/`update()`, declare it in your request class's `rules()`.

---

## `strict_capabilities`

**Type:** `bool` **Default:** `false`

```php
'strict_capabilities' => false,
```

Controls what `RelationSyncManager` does when incoming data contains a key that maps to a relation the model has declared (e.g. in `getHasManyRelations()`), but that key is not present in the incoming `$data` array.

- **`false` (default):** Silently skips any declared relation that has no matching key in `$data`. This is backward-compatible with the original codebase's behavior.
- **`true`:** Throws `Nexus\CrudEngine\Exceptions\UnsupportedCapabilityException` for any declared relation that is absent from `$data`. Use this in development environments to catch contracts that drift out of sync with incoming payloads.

---

## `files.disk`

**Type:** `string` **Default:** value of `config('filesystems.default', 'local')`

**Environment variable:** `CRUD_ENGINE_DISK`

```php
'files' => [
    'disk' => env('CRUD_ENGINE_DISK', config('filesystems.default', 'local')),
],
```

The Laravel filesystem disk name that `FileLifecycleService` uses for all file store, delete, and URL operations. Any disk defined in `config/filesystems.php` is valid, including `s3`, `local`, `public`, and custom drivers.

**Example — using S3:**

```env
CRUD_ENGINE_DISK=s3
```

**Common mistake:** Forgetting to update this after setting up S3. Files will be stored on the local disk while URLs are generated for S3 paths (or vice versa), leading to 404s or missing files.

---

## `relations.max_recursion_depth`

**Type:** `int` **Default:** `5`

```php
'relations' => [
    'max_recursion_depth' => 5,
],
```

The maximum number of levels `HasManySyncStrategy` and `HasOneSyncStrategy` will recurse into nested relations before throwing `RelationSyncException::maxRecursionDepthExceeded()`. The original codebase had no guard against infinite recursion — this setting exists specifically to prevent runaway recursion in deep or circular relation graphs.

A value of `5` means: model → child → grandchild → great-grandchild → great-great-grandchild → **stop**. Adjust upward only if your actual data model has legitimate deeper nesting.

---

## `statistics.query_strategy`

**Type:** `string` **Default:** `'eloquent'`

**Environment variable:** `CRUD_ENGINE_STATISTICS_STRATEGY`

```php
'statistics' => [
    'query_strategy' => env('CRUD_ENGINE_STATISTICS_STRATEGY', 'eloquent'),
],
```

Selects which implementation of `StatisticsQueryStrategyInterface` the container binds:

- **`'eloquent'` (default):** Uses `EloquentAggregateStrategy` — pure Eloquent, no extra Composer dependency, portable across MySQL, Postgres, and SQLite.
- **`'spatie'`:** Uses `SpatieQueryBuilderStrategy` — requires `spatie/laravel-query-builder` installed separately. Adds Spatie's filter/sort conventions to the statistics query. The service provider only binds this when `class_exists(Spatie\QueryBuilder\QueryBuilder::class)` is true; if the package is not installed, it falls back to `'eloquent'` silently.

---

## `statistics.cache_ttl`

**Type:** `int` (seconds) **Default:** `300`

**Environment variable:** `CRUD_ENGINE_STATISTICS_CACHE_TTL`

```php
'statistics' => [
    'cache_ttl' => env('CRUD_ENGINE_STATISTICS_CACHE_TTL', 300),
],
```

The number of seconds `AbstractStatisticsService::getStatistics()` caches its results via Laravel's cache layer. The cache key encodes the model class, date column, sum column, date range, and interval, so different queries never collide.

Set to `0` to disable caching entirely during development.

---

## `response_formatter`

**Type:** `class-string` **Default:** `Nexus\CrudEngine\Services\Responses\JsonResponseFormatter::class`

```php
'response_formatter' => \Nexus\CrudEngine\Services\Responses\JsonResponseFormatter::class,
```

The concrete class the container resolves when any code type-hints against `ResponseFormatterInterface`. Override this to change the entire response envelope shape — for example, to produce JSON:API-compliant output — without touching any Crud service.

**Custom formatter example:**

```php
// In AppServiceProvider::register():
$this->app->bind(
    \Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface::class,
    \App\Services\JsonApiResponseFormatter::class,
);
```

Or simply update the config value:

```php
'response_formatter' => \App\Services\JsonApiResponseFormatter::class,
```

---

## `log_operations`

**Type:** `bool` **Default:** `true`

```php
'log_operations' => true,
```

When `true`, `LogCrudOperationListener` is registered automatically during `CrudEngineServiceProvider::boot()` and subscribes to all seven package domain events, logging them via the PSR-3 logger.

Set to `false` if:
- You have registered your own listeners for all events and don't want duplicate log entries.
- You are running in a high-throughput environment where log volume from CRUD operations is a concern.

When `false`, the events are still dispatched — only the default listener is not registered. Your own listeners still fire normally.
