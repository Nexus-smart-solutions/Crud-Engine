# Nexus CRUD Engine

Generic CRUD services, file lifecycle management, nested relation synchronization, a statistics engine, and a handful of Laravel macros — extracted from an internal framework into a standalone, testable, dependency-injected Laravel package.

Supports Laravel 10, 11, and 12, on PHP 8.2+.

## Installation

```bash
composer require nexus/crud-engine
```

The service provider and facade are auto-discovered — no manual registration needed.

Publish the config and translation files (optional):

```bash
php artisan crud-engine:install
```

This is equivalent to running both of:

```bash
php artisan vendor:publish --tag=crud-engine-config
php artisan vendor:publish --tag=crud-engine-lang
```

## Core Concepts

The package never assumes anything about your models' structure. Instead, your Eloquent models opt in to behavior by implementing small **capability interfaces**, and every generic service asks a single `CapabilityRegistry` what a model supports — there's no scattered `instanceof` checking anywhere in your application code.

| Interface | Enables |
|---|---|
| `Nexus\CrudEngine\Contracts\Capabilities\FileUpload` | Automatic file store/delete/URL-rewrite for declared attributes |
| `Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations` | Automatic diff-based sync of declared hasMany relations |
| `Nexus\CrudEngine\Contracts\Capabilities\HasOneRelations` | Automatic update-or-create of declared hasOne relations |
| `Nexus\CrudEngine\Contracts\Capabilities\ManyToManyRelations` | Automatic `sync()` of declared belongsToMany relations |
| `Nexus\CrudEngine\Contracts\Capabilities\OriginalName` | Marker — keep the client's original filename instead of a hashed one |

## Quick Start

### 1. Implement capability interfaces on your model

```php
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Product extends Model implements FileUpload, HasManyRelations
{
    use HasFileUrlsTrait; // rewrites file attributes into full URLs in toArray()

    public function documentFullPathStore(): string
    {
        // Any structure you want — the package never assumes one.
        return 'products/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image'];
    }

    public function getHasManyRelations(): array
    {
        return ['variants'];
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }
}
```

### 2. Extend the abstract Crud service for your resource

```php
use Nexus\CrudEngine\Services\Crud\AbstractStoreService;

class ProductStoreService extends AbstractStoreService
{
    public function model(): string
    {
        return Product::class;
    }

    public function requestFile(): string
    {
        return ProductStoreRequest::class; // any class with a public rules(): array method
    }
}
```

### 3. Use it in a controller

```php
class ProductController extends Controller
{
    public function store(ProductStoreService $service)
    {
        $result = $service->store();

        return response()->success($result->data, $result->messages, $result->code);
    }
}
```

That's it — validation (only declared fields are persisted), file storage, and relation syncing for `variants` all happen automatically, in that order, with the file write deferred until after the database transaction commits.

## Updating and Deleting

```php
use Nexus\CrudEngine\Services\Crud\AbstractUpdateService;
use Illuminate\Database\Eloquent\Model;

class ProductUpdateService extends AbstractUpdateService
{
    public function __construct(private Product $product, /* ...parent deps via DI... */) { /* ... */ }

    public function model(): string { return Product::class; }
    public function requestFile(): string { return ProductUpdateRequest::class; }
    public function resolveModel(): Model { return $this->product; }
}
```

```php
use Nexus\CrudEngine\Services\Crud\AbstractBulkDeleteService;

class ProductBulkDeleteService extends AbstractBulkDeleteService
{
    public function model(): string
    {
        return Product::class;
    }
}
```

`AbstractBulkDeleteService` reads `ids` from the current request by default (override `resolveIds()` to change that), and safely handles a scalar `ids` value instead of throwing.

## Statistics

```php
use Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService;

class ProductStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string { return Product::class; }
    public function getDateColumn(): string { return 'created_at'; }
}

// In a controller:
$service->getStatistics('2026-01-01', '2026-01-31', 'days');
// => ['2026-01-01' => 3, '2026-01-02' => 0, ..., '2026-01-31' => 1]
```

Results are cached (`crud-engine.statistics.cache_ttl`, default 300 seconds). The query engine defaults to pure Eloquent (portable across MySQL/Postgres/SQLite); set `crud-engine.statistics.query_strategy` to `spatie` if you've separately installed `spatie/laravel-query-builder` and want its filtering conventions instead.

## Macros

Available globally once the package is installed, no setup required:

```php
// Migrations
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->status();       // tinyInteger('status')->default(1)
    $table->standardTime(); // created_at, updated_at, soft deletes
});

// Queries
Product::query()->customOrdering('name', 'asc'); // or relation: 'category.name'
Product::query()->datesFiltering();              // reads period_type/from_date/to_date from the request

// Carbon
Carbon::parseOrNow($maybeInvalidDate); // never throws

// Str
Str::snakeToTitle('product_name'); // "Product Name"
Str::humanText('product---name!!'); // "Product Name"

// Responses
return response()->success($data, ['crud-engine::responses.success.created']);
return response()->error('Something specific went wrong.');
```

## Events

Subscribe to any of these instead of, or in addition to, the package's default logging listener:

- `RecordCreated`, `RecordUpdated`, `RecordDeleted`, `RecordDeletionFailed`
- `FileStored`, `FileDeleted`
- `RelationSynced`

```php
Event::listen(RecordCreated::class, function (RecordCreated $event) {
    Cache::forget("product:{$event->model->getKey()}");
});
```

Disable the package's own logging listener with `crud-engine.log_operations = false` if you only want your own listeners.

## Overriding Defaults

Every behavior described above is bound against an interface and can be swapped from your own `AppServiceProvider`:

```php
$this->app->bind(
    \Nexus\CrudEngine\Contracts\Files\FilePathResolverInterface::class,
    \App\Services\TenantAwarePathResolver::class,
);

$this->app->bind(
    \Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface::class,
    \App\Services\JsonApiResponseFormatter::class,
);
```

## Configuration

After publishing, see `config/crud-engine.php` for: `strict_validation`, `strict_capabilities`, `files.disk`, `relations.max_recursion_depth`, `statistics.query_strategy`, `statistics.cache_ttl`, `response_formatter`, and `log_operations`.

## Testing

```bash
composer install
vendor/bin/phpunit
```

The test suite uses Orchestra Testbench against an in-memory SQLite database and includes dedicated regression tests for every bug fixed during the refactor (see CHANGELOG.md) — most notably the HasOne/HasMany recursion mix-up, the validation mass-assignment gap, the orphaned-file bug, and the original-filename path-traversal fix.

## License

MIT.
