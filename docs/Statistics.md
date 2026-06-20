# Statistics

Time-bucketed aggregate statistics (counts or sums, grouped by day/month/year) with a swappable query engine and built-in caching. Replaces `App\Core\Statistics\AbstractStatisticsRowsCounted`.

---

## Architecture

```
AbstractStatisticsService::getStatistics()
        │
        ├─ builds a StatisticsQuery DTO
        ├─ checks cache (crud-engine.statistics.cache_ttl)
        │       │ cache miss
        │       ▼
        ├─ StatisticsQueryStrategyInterface::execute($query)
        │       ├─ EloquentAggregateStrategy   (default — pure Eloquent, portable)
        │       └─ SpatieQueryBuilderStrategy  (optional — requires spatie/laravel-query-builder)
        │
        └─ fillEmptyBuckets() — zero-fills every day/month/year in the range
```

---

## `AbstractStatisticsService`

**Namespace:** `Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService`

### Constructor Dependencies (injected)

```php
public function __construct(
    StatisticsQueryStrategyInterface $queryStrategy,  // Eloquent or Spatie, per config
    CacheRepository $cache,                            // Illuminate\Contracts\Cache\Repository
    int $cacheTtlSeconds,                              // from crud-engine.statistics.cache_ttl
)
```

### Abstract Methods (you must implement)

```php
abstract public function getModelClass(): string;   // class-string<Model>
abstract public function getDateColumn(): string;    // e.g. 'created_at'
```

### Overridable Methods

```php
public function getSumColumn(): ?string      // default: null (counts rows instead of summing)
public function getScopes(): array           // default: [] — local scope method names to apply
public function getAllowedFilters(): array   // default: [] — Spatie filter names (Spatie strategy only)
```

### Public API

```php
public function getStatistics(string $startDate, string $endDate, string $interval = 'days'): array
```

| Parameter | Type | Description |
|---|---|---|
| `$startDate` | `string` | Start of the range, e.g. `'2026-01-01'` |
| `$endDate` | `string` | End of the range, e.g. `'2026-01-31'` |
| `$interval` | `string` | `'days'`, `'months'`, or `'years'` (default `'days'`) |

**Returns:** `array<string, float|int>` — every bucket in the range is present, even if zero.

---

## Minimal Subclass

```php
use Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService;

class PostStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string
    {
        return Post::class;
    }

    public function getDateColumn(): string
    {
        return 'created_at';
    }
}
```

**Usage:**

```php
$service = app(PostStatisticsService::class);

$counts = $service->getStatistics('2026-01-01', '2026-01-31', 'days');
// [
//   '2026-01-01' => 3,
//   '2026-01-02' => 0,
//   '2026-01-03' => 5,
//   ...
//   '2026-01-31' => 1,
// ]
```

---

## Summing Instead of Counting

Override `getSumColumn()` to sum a numeric column instead of counting rows:

```php
class OrderRevenueStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string { return Order::class; }
    public function getDateColumn(): string { return 'paid_at'; }
    public function getSumColumn(): ?string { return 'total_amount'; }
}
```

```php
$revenue = app(OrderRevenueStatisticsService::class)
    ->getStatistics('2026-01-01', '2026-12-31', 'months');
// ['2026-01' => 45230.50, '2026-02' => 51200.00, ...]
```

---

## Applying Local Scopes

```php
class PublishedPostStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string { return Post::class; }
    public function getDateColumn(): string { return 'created_at'; }

    public function getScopes(): array
    {
        return ['published'];   // calls Post::query()->published()
    }
}
```

The `Post` model must define `scopePublished(Builder $query)`.

---

## Interval Bucket Formats

| `$interval` | Bucket key format | Example |
|---|---|---|
| `'days'` | `Y-m-d` | `'2026-01-15'` |
| `'months'` | `Y-m` | `'2026-01'` |
| `'years'` | `Y` | `'2026'` |

---

## Caching Behavior

`getStatistics()` wraps the entire query (strategy execution + zero-fill) in `$cache->remember($key, $ttl, $callback)`. The cache key is built from:

```php
implode(':', [
    'crud-engine-stats',
    str_replace('\\', '_', $modelClass),
    $dateColumn,
    $sumColumn ?? 'count',
    $startDate,
    $endDate,
    $interval,
]);
```

Different combinations of date range, interval, column, and model never collide. The TTL comes from `crud-engine.statistics.cache_ttl` (default `300` seconds, configurable via `CRUD_ENGINE_STATISTICS_CACHE_TTL`).

**To bypass the cache during development**, set:

```env
CRUD_ENGINE_STATISTICS_CACHE_TTL=0
```

---

## Switching to the Spatie Strategy

1. Install the optional dependency:

```bash
composer require spatie/laravel-query-builder
```

2. Set the strategy in `.env`:

```env
CRUD_ENGINE_STATISTICS_STRATEGY=spatie
```

3. Declare allowed filters in your subclass:

```php
class PostStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string { return Post::class; }
    public function getDateColumn(): string { return 'created_at'; }

    public function getAllowedFilters(): array
    {
        return ['status', 'author_id'];
    }
}
```

If `spatie/laravel-query-builder` is not installed, the service provider's `class_exists()` guard silently falls back to `EloquentAggregateStrategy` regardless of the config value — your application will not break, but Spatie-specific filtering will not apply.

---

## Database Portability

The original `AbstractStatisticsRowsCounted` interpolated `DATE_FORMAT($column, '%Y-%m-%d')` directly into raw SQL — a MySQL-only function that does not exist on Postgres (`TO_CHAR`) or SQLite. `EloquentAggregateStrategy` avoids this entirely: it fetches raw date values with a plain `whereBetween` and groups them in PHP using `Carbon::parse()`. This works identically across MySQL, Postgres, and SQLite without any database-specific function calls.

---

## Common Mistakes

**Calling `getStatistics()` from inside a request and expecting fresh data immediately after a write.**

Because results are cached, a `Post` created moments ago may not appear in a `getStatistics()` call until the TTL expires. Either lower the TTL, set it to `0` for near-real-time dashboards, or manually flush the relevant cache key after writes (e.g. via the `RecordCreated` event listener).

**Forgetting that `$sumColumn` rows must be numeric.**

`EloquentAggregateStrategy` casts the sum column value with `(float)`. Non-numeric strings cast to `0.0` silently — no exception is thrown.

**Expecting filters to apply with the Eloquent strategy.**

`getAllowedFilters()` is only consumed by `SpatieQueryBuilderStrategy`. `EloquentAggregateStrategy` ignores it entirely — there is no filtering mechanism in the default strategy beyond `getScopes()`.
