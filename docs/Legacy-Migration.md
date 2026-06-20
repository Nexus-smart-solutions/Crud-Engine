# Legacy Migration — From `App\Core` to `Nexus\CrudEngine`

This guide maps every class from the original `App\Core` codebase to its package replacement, with before/after code for each migration.

---

## Complete Class Mapping

| Original (`App\Core\...`) | Replacement (`Nexus\CrudEngine\...`) |
|---|---|
| `Classes\HandleFiles\StoragePictures` (static) | `Contracts\Files\FileLifecycleServiceInterface` (injected, default impl `Services\Files\FileLifecycleService`) |
| `Classes\HandleRelation\HandleRelationHasMany` (static) | `Strategies\Relations\HasManySyncStrategy`, dispatched via `RelationSyncManager` |
| `Classes\HandleRelation\HandleRelationHasOne` (static) | `Strategies\Relations\HasOneSyncStrategy` |
| `Classes\HandleRelation\HandleRelationManyToMany` (static) | `Strategies\Relations\ManyToManySyncStrategy` |
| `Classes\StoringData\AbstractClassHandleStoreData` | `Services\Crud\AbstractStoreService` |
| `Classes\UpdatingData\AbstractClassHandleUpdate` | `Services\Crud\AbstractUpdateService` |
| `Classes\DeletingData\AbstractClassHandleDelete` | `Services\Crud\AbstractDeleteService` |
| `Classes\DeletingData\BulkDestroyService` | `Services\Crud\AbstractBulkDeleteService` |
| `Traits\DataArrayFromRequestTrait` | `Contracts\Validation\RequestValidatorInterface` (injected service, no longer a trait) |
| `Traits\FilesHandleForCrud` | Folded into `Services\Files\FileLifecycleService`, invoked via DI inside the Crud services |
| `Traits\RelationsHandleForCrud` | Folded into `Services\Relations\RelationSyncManager` |
| `Traits\RelationChecks` | Folded into `Services\Capabilities\CapabilityRegistry` |
| `Statistics\AbstractStatisticsRowsCounted` | `Services\Statistics\AbstractStatisticsService` |
| `Interfaces\FileUpload` | `Contracts\Capabilities\FileUpload` (same method signatures) |
| `Interfaces\HasManyRelations` | `Contracts\Capabilities\HasManyRelations` |
| `Interfaces\HasOneRelations` | `Contracts\Capabilities\HasOneRelations` |
| `Interfaces\ManyToManyRelations` | `Contracts\Capabilities\ManyToManyRelations` |
| `Interfaces\OriginalName` | `Contracts\Capabilities\OriginalName` |
| `Interfaces\Payment\PaymentManagerInterface` | **Not migrated** — no implementation or consumer existed; out of scope |
| Standalone `HandleToArrayTrait.php` (App\Models) | `Traits\HasFileUrlsTrait` |
| `Macros\BlueprintMacro.php` | `Macros\BlueprintMacros` (auto-registered) |
| `Macros\BuilderMacro.php` | `Macros\BuilderMacros` (auto-registered, security-fixed) |
| `Macros\CarbonMacro.php` | `Macros\CarbonMacros` (auto-registered) |
| `Macros\ReponseMacro.php` *(typo in original filename)* | `Macros\ResponseMacros` (auto-registered, typo fixed) |
| `Macros\StrMacro.php` | `Macros\StrMacros` (auto-registered) |
| `App\Exceptions\CustomValidationException` (referenced, never provided) | `Exceptions\CrudValidationException` (self-rendering via `Responsable`) |

---

## Migration Example 1 — File Handling

**Before:**

```php
use App\Core\Classes\HandleFiles\StoragePictures;

// Somewhere in a controller or service:
$url = StoragePictures::customUrl($post, $post->cover_image);
StoragePictures::deleteFile($post, 'cover_image');
StoragePictures::storeFile($request->file('cover_image'), $post, 'cover_image');
```

**After:**

```php
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;

class PostController extends Controller
{
    public function __construct(
        private readonly FileLifecycleServiceInterface $files,
    ) {}

    public function someAction(Post $post, Request $request)
    {
        $url = $this->files->url($post, $post->cover_image);
        $this->files->delete($post, 'cover_image');
        $this->files->store($post, 'cover_image', $request->file('cover_image'));
    }
}
```

**Behavioral difference:** The original `deleteFile()` removed the physical file but never nulled the database column (Bug 4.2). `FileLifecycleServiceInterface::delete()` always nulls and saves the attribute as part of the same call.

---

## Migration Example 2 — Store Service

**Before:**

```php
use App\Core\Classes\StoringData\AbstractClassHandleStoreData;

class PostStoreService extends AbstractClassHandleStoreData
{
    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostRequest::class; }
}
```

**After:**

```php
use Nexus\CrudEngine\Services\Crud\AbstractStoreService;

class PostStoreService extends AbstractStoreService
{
    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostRequest::class; }
}
```

**The subclass shape is identical** — only the `extends` target changes. This was a deliberate design goal: existing subclasses needed minimal changes.

**Behavioral differences:**
- Validation now returns only declared fields (Bug 4.1 fix) — if your `PostRequest::rules()` was missing a field you relied on, add it.
- File writes happen after the DB transaction commits, not inside it (Bug 4.6 fix).
- Logging is now event-driven (`RecordCreated` + `LogCrudOperationListener`) instead of inline `Log::info()` calls baked into the base class.

---

## Migration Example 3 — Update Service

**Before:**

```php
use App\Core\Classes\UpdatingData\AbstractClassHandleUpdate;

class PostUpdateService extends AbstractClassHandleUpdate
{
    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostRequest::class; }
    // model resolution handled differently per original implementation
}
```

**After:**

```php
use Nexus\CrudEngine\Services\Crud\AbstractUpdateService;
use Illuminate\Database\Eloquent\Model;

class PostUpdateService extends AbstractUpdateService
{
    private ?Post $post = null;

    public function forPost(Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostRequest::class; }

    public function resolveModel(): Model
    {
        return $this->post ?? throw new \LogicException('Call forPost() first.');
    }
}
```

**New requirement:** `resolveModel(): Model` is now an explicit abstract method you must implement — the original codebase's model-resolution approach varied by subclass; this package standardizes it as one required method.

---

## Migration Example 4 — Delete & Bulk Delete

**Before:**

```php
use App\Core\Classes\DeletingData\AbstractClassHandleDelete;
use App\Core\Classes\DeletingData\BulkDestroyService;

class PostDeleteService extends AbstractClassHandleDelete { /* ... */ }
class PostBulkDeleteService extends BulkDestroyService { /* ... */ }
```

**After:**

```php
use Nexus\CrudEngine\Services\Crud\AbstractDeleteService;
use Nexus\CrudEngine\Services\Crud\AbstractBulkDeleteService;
use Illuminate\Database\Eloquent\Collection;

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

class PostBulkDeleteService extends AbstractBulkDeleteService
{
    public function model(): string { return Post::class; }
    // resolveTargets() and ID resolution are already implemented in the base class
}
```

**Behavioral differences:**
- A scalar `ids` request value (e.g. `?ids=5`) no longer throws a `TypeError` (Bug 4.5 fix).
- Failed deletions dispatch `RecordDeletionFailed` with the original exception instead of being silently swallowed (Bug 4.7 fix).
- Partial bulk-delete results now return HTTP `207` with a structured `failed_ids` array instead of an ad-hoc shape.

---

## Migration Example 5 — Relation Capability Interfaces

**Before:**

```php
use App\Core\Interfaces\HasManyRelations;
use App\Core\Interfaces\HasOneRelations;
use App\Core\Interfaces\FileUpload;

class Post extends Model implements HasManyRelations, FileUpload
{
    public function getHasManyRelations(): array { return ['comments']; }
    public function documentFullPathStore(): string { return 'posts/'.$this->id; }
    public function requestKeysForFile(): array { return ['cover_image']; }
}
```

**After:**

```php
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;

class Post extends Model implements HasManyRelations, FileUpload
{
    public function getHasManyRelations(): array { return ['comments']; }
    public function documentFullPathStore(): string { return 'posts/'.$this->id; }
    public function requestKeysForFile(): array { return ['cover_image']; }
}
```

**Only the `use` statements change.** Every method signature in every capability interface is identical between the original and the package — this was a deliberate compatibility decision so model classes require only an import change, not a logic change.

---

## Migration Example 6 — `HandleToArrayTrait`

**Before:**

```php
namespace App\Models;

use App\Core\Classes\HandleFiles\StoragePictures;
use App\Core\Interfaces\FileUpload;

trait HandleToArrayTrait
{
    public function toArray()
    {
        $data = parent::toArray();
        if ($this instanceof FileUpload) {
            foreach ($this->requestKeysForFile() as $file) {
                if (isset($data[$file]) && $data[$file] != null) {
                    $data[$file] = StoragePictures::customUrl($this, $data[$file]);
                }
            }
        }
        return $data;
    }
}
```

**After:**

```php
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;

class Post extends Model implements FileUpload
{
    use HasFileUrlsTrait;
    // ...
}
```

Replace `use App\Models\HandleToArrayTrait;` with `use Nexus\CrudEngine\Traits\HasFileUrlsTrait;` on every model. Behavior is identical (URL rewriting in `toArray()`), implemented via `FileLifecycleServiceInterface::url()` internally instead of the static `StoragePictures::customUrl()`.

---

## Migration Example 7 — Macros

**Before:** Macros were manually registered, typically in `AppServiceProvider::boot()`:

```php
// AppServiceProvider.php
public function boot()
{
    require base_path('app/Core/Macros/BlueprintMacro.php');
    require base_path('app/Core/Macros/BuilderMacro.php');
    require base_path('app/Core/Macros/CarbonMacro.php');
    require base_path('app/Core/Macros/ReponseMacro.php'); // note original typo
    require base_path('app/Core/Macros/StrMacro.php');
}
```

**After:** Nothing to do. All five macros are registered automatically by `CrudEngineServiceProvider::boot()` once the package is installed via Composer. **Remove the manual `require` calls** from your `AppServiceProvider` — leaving them in place alongside the package would attempt to re-register the same macro names and throw.

---

## Migration Example 8 — Statistics

**Before:**

```php
use App\Core\Statistics\AbstractStatisticsRowsCounted;

class PostStatistics extends AbstractStatisticsRowsCounted
{
    // relied on MySQL-specific DATE_FORMAT() under the hood
}
```

**After:**

```php
use Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService;

class PostStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string { return Post::class; }
    public function getDateColumn(): string { return 'created_at'; }
}
```

**Behavioral differences:**
- Results are now cached (TTL configurable) — the original re-ran the aggregate query on every call.
- The query engine is portable across MySQL/Postgres/SQLite by default (no `DATE_FORMAT()`).
- Constructor injection replaces direct `spatie/laravel-query-builder` coupling — Spatie is now fully optional.

---

## What Was Deliberately NOT Migrated

**`PaymentManagerInterface`** — excluded per explicit confirmation during the planning phase. It had no implementation or consumer anywhere in the original codebase and was unrelated to the CRUD/File/Relation framework. If a payment module is needed later, it should be a separate package.

---

## Migration Checklist

- [ ] `composer require nexus/crud-engine`
- [ ] Remove manual macro `require` statements from `AppServiceProvider`
- [ ] Replace `App\Core\Interfaces\*` imports with `Nexus\CrudEngine\Contracts\Capabilities\*` on every model (method signatures are unchanged)
- [ ] Replace `use App\Models\HandleToArrayTrait;` with `use Nexus\CrudEngine\Traits\HasFileUrlsTrait;`
- [ ] Change every Crud service's `extends App\Core\Classes\...` to `extends Nexus\CrudEngine\Services\Crud\Abstract*Service`
- [ ] Add `resolveModel(): Model` to update services (now a required abstract method)
- [ ] Add `resolveTargets(): Collection` to delete services (now a required abstract method, unless using `AbstractBulkDeleteService`, which implements it for you)
- [ ] Audit every request class's `rules()` for fields your application currently relies on being passed through unvalidated — `strict_validation` is unconditionally on
- [ ] Publish config (`php artisan crud-engine:install`) and review `crud-engine.files.disk`
- [ ] Run your test suite — the regression-fixed bugs (4.1–4.7, S2, S4) may surface behavior differences if your application was depending on the old buggy behavior
