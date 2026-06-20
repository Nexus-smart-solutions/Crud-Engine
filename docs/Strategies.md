# Strategies

Seven strategy classes across three categories: file naming, relation synchronization, and statistics querying.

---

## File Naming Strategies

Both implement `FileNamingStrategyInterface`:

```php
public function generateName(UploadedFile $file, Model $model): string;
```

`FileLifecycleService` selects between them at runtime by asking `CapabilityRegistry::usesOriginalFilename($model)`.

---

### `HashedFilenameStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Files\HashedFilenameStrategy`

**Selected when:** Model does **not** implement `OriginalName` (the default for all models).

**Behavior:** Delegates to `$file->hashName()` — Laravel's own collision-safe hashed filename generator. The result is a random hex string with the original file extension, e.g. `a1b2c3d4e5f6.jpg`.

**No sanitization needed** — the filename is entirely system-generated and carries no client-supplied input.

**Example output:** `f47ac10b58cc4372a5670e02b2c3d479.jpg`

---

### `OriginalFilenameStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Files\OriginalFilenameStrategy`

**Selected when:** Model implements `OriginalName`.

**Purpose:** Preserves the original client filename while sanitizing it against path traversal and injection attacks (Security Finding S4 fix).

**Sanitization pipeline (applied in order):**

1. `basename()` — strips any directory component, defeating `../../etc/passwd`-style traversal
2. Strip control characters and null bytes (`/[\x00-\x1F\x7F]/`)
3. Allow-list remaining characters to `[A-Za-z0-9 ._-]` only
4. Collapse repeated dots (`\.{2,}` → `.`)
5. Replace spaces with underscores
6. Trim leading/trailing `._-`
7. If result is empty → fall back to `$file->hashName()`

**Example transformations:**

| Client filename | Stored as |
|---|---|
| `my report.pdf` | `my_report.pdf` |
| `../../../etc/passwd` | `passwd` |
| `file\x00name.jpg` | `filename.jpg` |
| `weird;rm -rf$(name).jpg` | `weirdrm-rfname.jpg` |
| `../../..` | `<hashed fallback>` |

**Collision risk:** Two uploads with the same sanitized filename for the same model overwrite each other. This is inherent to "preserve original filename" semantics. If you need both original names and collision safety, you must implement a custom `FileNamingStrategyInterface` that appends a timestamp or UUID.

---

## Relation Sync Strategies

All three implement `RelationSyncStrategyInterface`:

```php
public function sync(RelationSyncContext $context): void;
```

All three are dispatched by `RelationSyncManager`, never called directly from application code.

---

### `HasManySyncStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Relations\HasManySyncStrategy`

**Purpose:** Diff-based sync for `hasMany` relations.

**Constructor:**

```php
public function __construct(
    CapabilityRegistryInterface $capabilities,
    FileLifecycleServiceInterface $files,
    Container $container,        // lazy resolution of RelationSyncManagerInterface (circular dep)
    int $maxRecursionDepth,      // from crud-engine.relations.max_recursion_depth
)
```

**`sync()` execution order:**

1. Guard recursion depth — throws `RelationSyncException::maxRecursionDepthExceeded()` if exceeded
2. Guard relation method existence — throws `RelationSyncException::relationMethodMissing()` if missing
3. `normalizeIncomingRows()` — returns `[]` for non-array or empty input; wraps a single associative array in a list
4. Extract incoming `$id` values from rows that have one
5. **Bulk-load** existing related rows via `whereIn` (N+1 fix — original did one `find()` per row)
6. Delete orphans — existing rows whose ID is absent from the incoming payload
7. For each incoming row: update (if ID found in loaded set) or create (if no ID)
8. `handleChildFiles()` — applies file values on each child row if it implements `FileUpload`
9. `handleChildRecursion()` — recurses via `$container->make(RelationSyncManagerInterface::class)->syncAll($child, $row, $depth + 1)` if the child has any relation capability

**Incoming payload shape:**

```json
{
  "comments": [
    { "id": 5, "body": "Updated comment" },
    { "body": "New comment — no id" }
  ]
}
```

A row with no `id` → **create**. A row with a known `id` → **update**. An existing row whose `id` is not in the payload → **delete**.

**Common mistake:** Sending a scalar ID instead of an array of row objects for a hasMany relation. The strategy expects an array of row arrays, not an array of IDs.

---

### `HasOneSyncStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Relations\HasOneSyncStrategy`

**Purpose:** Update-or-create sync for `hasOne` relations. Also fixes Bug 4.3 from the original codebase.

**Constructor:** Same shape as `HasManySyncStrategy`.

**`sync()` execution order:**

1. Guard recursion depth
2. Guard relation method existence
3. Normalize `incomingData` to array — returns early if empty
4. Remove `id` from the incoming data (the ID is derived from the relation, not from client input)
5. `$relation->first()` — check if a related record exists
6. If exists: `fill()` + `save()`; if not: `create()`
7. `handleChildFiles()` on the child
8. `handleChildRecursion()` — recurse via the container (same circular-dependency pattern as `HasManySyncStrategy`)

**Bug 4.3 fix:** The original `HandleRelationHasOne` recursed by calling `$existingRecord->getHasManyRelations()` inside the hasOne handler — a copy-paste mistake. Here, recursion goes through `RelationSyncManager::syncAll()`, which asks `CapabilityRegistry` what the child model actually supports. A model that implements only `HasOneRelations` (not `HasManyRelations`) recurses correctly through its own hasOne relations without error.

**Incoming payload shape:**

```json
{
  "profile": {
    "bio": "Backend engineer",
    "settings": { "theme": "dark" }
  }
}
```

---

### `ManyToManySyncStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Relations\ManyToManySyncStrategy`

**Purpose:** Thin wrapper over Eloquent's `sync()` for `belongsToMany` relations.

**Constructor:** No dependencies.

**`sync()` execution:**

1. Guard relation method existence
2. `normalizeIds()` — filters the incoming data to non-null, non-empty values
3. `$model->{$relationName}()->sync($ids)`

**`normalizeIds()` behavior:** Accepts any array — filters out `null` and `''`. Non-array input is treated as empty and calls `sync([])`, which detaches all related records.

**Incoming payload shape — array of IDs, not row objects:**

```json
{
  "tags": [1, 3, 7]
}
```

**Common mistake:** Passing an array of row objects (like hasMany expects) instead of an array of IDs. `ManyToManySyncStrategy` passes the array directly to `sync()` — Eloquent will throw or silently misbehave if it receives non-integer values.

---

## Statistics Strategies

Both implement `StatisticsQueryStrategyInterface`:

```php
public function execute(StatisticsQuery $query): array;
// Returns array<string, float|int> — unordered raw buckets, no zero-fill
```

`AbstractStatisticsService` handles zero-filling and caching — strategies only query.

---

### `EloquentAggregateStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Statistics\EloquentAggregateStrategy`

**Selected when:** `crud-engine.statistics.query_strategy = 'eloquent'` (default).

**Constructor:** No dependencies.

**Behavior:**

1. Instantiates the model and builds a query with `whereBetween($dateColumn, [$start, $end])`
2. Applies named local scopes from `$query->scopes`
3. Fetches only the date column (and sum column if set)
4. Groups in PHP using `Carbon::parse()` + `format()` for the interval
5. Returns raw `{bucket_key => value}` map (no zero-fill)

**Counting vs summing:**

- `$query->sumColumn === null` → counts rows (increments by 1 per row)
- `$query->sumColumn !== null` → sums the column value per row (cast to `float`)

**Portability:** Uses pure Eloquent and PHP-side grouping — no `DATE_FORMAT()`, no `EXTRACT()`, no database-specific functions. Works identically on MySQL, Postgres, SQLite.

---

### `SpatieQueryBuilderStrategy`

**Namespace:** `Nexus\CrudEngine\Strategies\Statistics\SpatieQueryBuilderStrategy`

**Selected when:** `crud-engine.statistics.query_strategy = 'spatie'` AND `spatie/laravel-query-builder` is installed.

**Constructor:** Throws `CrudEngineException` immediately if `QueryBuilder::class` does not exist — defense-in-depth in case this class is instantiated directly.

**Behavior:** Same grouping logic as `EloquentAggregateStrategy`, but builds the query through `Spatie\QueryBuilder\QueryBuilder::for()` with `allowedFilters($query->allowedFilters)`, enabling Spatie's filter conventions on statistics endpoints.

**Filters are only applied** when the `SpatieQueryBuilderStrategy` is active — they are ignored by `EloquentAggregateStrategy`.

**When to use:** Only if your statistics endpoint already uses Spatie's query builder URL conventions and you need the same filters to apply to statistical aggregates.
