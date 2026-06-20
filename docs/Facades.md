# Facades

The package ships exactly one facade.

---

## `CrudEngine`

**Namespace:** `Nexus\CrudEngine\Facades\CrudEngine`

**Facade accessor:** `CapabilityRegistryInterface::class`

**Alias registered by Composer auto-discovery:** `CrudEngine` (via `extra.laravel.aliases` in `composer.json`)

**Purpose:** Optional convenience facade for ad-hoc capability introspection — for example, inside Blade templates, console commands, or quick debugging where constructor injection is inconvenient.

> This facade is sugar, never required. The package's primary API is constructor injection against the contracts in `Nexus\CrudEngine\Contracts`. Every class inside the package itself uses constructor injection — the facade exists purely for consuming-application convenience.

---

### Public API

The facade proxies every method on `CapabilityRegistryInterface`:

```php
/**
 * @method static bool supportsFileUpload(object $model)
 * @method static bool supportsHasMany(object $model)
 * @method static bool supportsHasOne(object $model)
 * @method static bool supportsManyToMany(object $model)
 * @method static bool usesOriginalFilename(object $model)
 */
```

| Method | Parameters | Returns | Description |
|---|---|---|---|
| `supportsFileUpload($model)` | `object $model` | `bool` | True if `$model instanceof FileUpload` |
| `supportsHasMany($model)` | `object $model` | `bool` | True if `$model instanceof HasManyRelations` |
| `supportsHasOne($model)` | `object $model` | `bool` | True if `$model instanceof HasOneRelations` |
| `supportsManyToMany($model)` | `object $model` | `bool` | True if `$model instanceof ManyToManyRelations` |
| `usesOriginalFilename($model)` | `object $model` | `bool` | True if `$model instanceof OriginalName` |

---

### Usage Examples

**In a Blade template:**

```blade
@if (CrudEngine::supportsFileUpload($post))
    <img src="{{ $post->cover_image }}" alt="Cover">
@endif
```

**In a console command:**

```php
class InspectModelCommand extends Command
{
    protected $signature = 'inspect:model {class}';

    public function handle(): void
    {
        $class = $this->argument('class');
        $instance = new $class();

        $this->table(['Capability', 'Supported'], [
            ['File Upload', CrudEngine::supportsFileUpload($instance) ? 'Yes' : 'No'],
            ['Has Many', CrudEngine::supportsHasMany($instance) ? 'Yes' : 'No'],
            ['Has One', CrudEngine::supportsHasOne($instance) ? 'Yes' : 'No'],
            ['Many To Many', CrudEngine::supportsManyToMany($instance) ? 'Yes' : 'No'],
        ]);
    }
}
```

**Using the fully-qualified facade class without the alias:**

```php
use Nexus\CrudEngine\Facades\CrudEngine;

if (CrudEngine::supportsHasMany($post)) {
    // ...
}
```

---

### Equivalent Constructor-Injected Form

Every facade call has a direct, equally valid constructor-injected equivalent:

```php
// Facade:
CrudEngine::supportsFileUpload($post);

// Equivalent, preferred inside package-style services:
public function __construct(
    private readonly CapabilityRegistryInterface $capabilities,
) {}

public function check(Model $post): bool
{
    return $this->capabilities->supportsFileUpload($post);
}
```

---

### Common Mistakes

**Using the facade inside a constructor-injectable class.**

Inside any service, strategy, or class resolved by the container, prefer constructor-injecting `CapabilityRegistryInterface` directly. The facade adds an indirection layer (`Facade::__callStatic` → container resolution) with no benefit over direct injection, and makes the dependency invisible to static analysis and testing tools that look at constructors.

**Assuming the facade gives access to more than the capability registry.**

The facade's accessor is `CapabilityRegistryInterface::class` only. It does **not** expose `FileLifecycleServiceInterface`, `RelationSyncManagerInterface`, or any other contract. For those, inject the interface directly or resolve via `app(SomeInterface::class)`.

**Calling the facade in a context where the container is unavailable.**

Like any Laravel facade, `CrudEngine::` requires the application container to be bootstrapped. It will not work in plain PHP scripts outside the Laravel bootstrap process.

---

### Testing with the Facade

`Facade::fake()` style mocking works as with any Laravel facade:

```php
use Nexus\CrudEngine\Facades\CrudEngine;

CrudEngine::shouldReceive('supportsFileUpload')
    ->once()
    ->with($post)
    ->andReturn(true);
```

Prefer binding a test double directly to `CapabilityRegistryInterface` in the container when testing classes that use constructor injection (the normal case) — reserve facade mocking for code paths that specifically use the `CrudEngine::` facade syntax (Blade templates, console commands).
