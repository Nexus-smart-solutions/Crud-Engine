# Testing

The package ships a full Orchestra Testbench test suite: 16 test files across `tests/Unit` and `tests/Feature`, plus shared fixtures under `tests/Fixtures`.

---

## Running the Suite

```bash
composer install
vendor/bin/phpunit
```

`phpunit.xml` defines two suites:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

Run a single suite:

```bash
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

Run a single test file:

```bash
vendor/bin/phpunit tests/Unit/Relations/HasOneSyncStrategyTest.php
```

---

## `TestCase` Bootstrap

**Namespace:** `Nexus\CrudEngine\Tests\TestCase`

Extends `Orchestra\Testbench\TestCase`. Every test class in the suite extends this.

```php
abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [CrudEngineServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('filesystems.disks.testing', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/crud-engine-tests',
        ]);
        $app['config']->set('filesystems.default', 'testing');
        $app['config']->set('crud-engine.files.disk', 'testing');
    }
}
```

Database is in-memory SQLite. Filesystem disk `'testing'` points at a temp directory and is the default disk for the test environment.

---

## Fixtures

### `CreatesFixtureSchema` Trait

**Path:** `tests/Fixtures/CreatesFixtureSchema.php`

Builds seven tables against the in-memory SQLite connection: `articles`, `comments`, `tags`, `article_tag`, `profiles`, `settings`, `documents`. Used by every test that needs real database rows.

```php
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;

final class MyTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createFixtureSchema();
    }
}
```

### Fixture Models (`tests/Fixtures/Models/`)

| Model | Implements | Purpose |
|---|---|---|
| `Article` | `FileUpload`, `HasManyRelations`, `HasOneRelations`, `ManyToManyRelations` | Primary root model — exercises all three relation types plus file upload in one fixture |
| `Comment` | `FileUpload` | HasMany child of `Article`; also has a file attribute to test nested file handling |
| `Tag` | — (plain model) | Many-to-many target for `Article::tags()` |
| `Profile` | `HasOneRelations` **only** | **Critical fixture** — deliberately omits `HasManyRelations` to regression-test Bug 4.3 |
| `Setting` | — (plain model) | Grandchild leaf in the `Article → Profile → Setting` recursion chain |
| `Document` | `FileUpload`, `OriginalName` | Exercises `OriginalFilenameStrategy` for the path-traversal regression tests |

### Fixture Requests (`tests/Fixtures/Requests/`)

`ArticleRequest` declares only `title`, `body`, `cover_image` in `rules()` — deliberately omitting fields like `is_admin` to regression-test Bug 4.1 (mass-assignment).

### Fixture Services (`tests/Fixtures/Services/`)

| Service | Extends | Notes |
|---|---|---|
| `ArticleStoreService` | `AbstractStoreService` | Minimal subclass — only `model()` and `requestFile()` |
| `ArticleUpdateService` | `AbstractUpdateService` | Uses a `forArticle(Article $article)` fluent setter instead of constructor injection of the target model, keeping the constructor purely container-resolvable |
| `ArticleDeleteService` | `AbstractDeleteService` | Uses a `forTargets(Collection $targets)` fluent setter |
| `ArticleBulkDeleteService` | `AbstractBulkDeleteService` | No extra wiring — `model()` only |
| `ArticleStatisticsService` | `AbstractStatisticsService` | `getModelClass()` + `getDateColumn()` only |

---

## Unit Tests (`tests/Unit/`)

| File | Tests |
|---|---|
| `Capabilities/CapabilityRegistryTest.php` | Each `supports*()` method against fixture models with known capabilities |
| `Files/FileLifecycleServiceTest.php` | `store()`, `delete()` (Bug 4.2 regression), `applyIncomingValue()` for all three input types |
| `Files/OriginalFilenameStrategyTest.php` | Path traversal stripping, control character stripping, allow-list enforcement, space replacement, hashed fallback (Security S4 regression) |
| `Relations/HasManySyncStrategyTest.php` | Create/update diff sync, orphan deletion, empty-array full deletion |
| `Relations/HasOneSyncStrategyTest.php` | **Bug 4.3 regression** — recursion into a model implementing only `HasOneRelations`; update-without-duplicate test |
| `Responses/JsonResponseFormatterTest.php` | Envelope shape, plain-message passthrough, translation-key resolution, fallback-to-raw-key behavior |
| `Statistics/EloquentAggregateStrategyTest.php` | Day-grouping counts, empty-result handling |
| `Validation/LaravelRequestValidatorTest.php` | **Bug 4.1 regression** — undeclared field exclusion; required-field failure throws `CrudValidationException` |

### Key Regression Test — Bug 4.3

```php
// tests/Unit/Relations/HasOneSyncStrategyTest.php
public function test_syncing_a_has_one_relation_recurses_correctly_into_a_model_that_only_implements_has_one_relations(): void
{
    $article = Article::create(['title' => 'Hello world']);
    $strategy = $this->app->make(HasOneSyncStrategy::class);

    // Article -> profile (hasOne) -> settings (hasOne, nested).
    // Profile implements HasOneRelations only — no getHasManyRelations()
    // method exists on it at all.
    $strategy->sync(new RelationSyncContext(
        model: $article,
        relationName: 'profile',
        incomingData: ['bio' => 'Backend engineer', 'settings' => ['theme' => 'dark']],
        type: RelationType::HasOne,
    ));

    $setting = $article->profile()->first()->settings()->first();

    $this->assertNotNull($setting, 'Bug 4.3: should not throw a fatal error.');
    $this->assertSame('dark', $setting->theme);
}
```

Under the original buggy code, this exact scenario threw a fatal "call to undefined method `getHasManyRelations`" error, because `Profile` never defined that method.

### Key Regression Test — Bug 4.1

```php
// tests/Unit/Validation/LaravelRequestValidatorTest.php
public function test_validate_returns_only_declared_fields_closing_the_mass_assignment_gap(): void
{
    $this->app->instance('request', Request::create('/articles', 'POST', [
        'title'    => 'Hello world',
        'body'     => 'Some body text',
        'is_admin' => true, // not declared in ArticleRequest::rules()
    ]));

    $validator = $this->app->make(RequestValidatorInterface::class);
    $data = $validator->validate(ArticleRequest::class);

    $this->assertArrayHasKey('title', $data);
    $this->assertArrayNotHasKey('is_admin', $data);
}
```

---

## Feature Tests (`tests/Feature/`)

| File | Tests |
|---|---|
| `StoreServiceTest.php` | End-to-end create via `ArticleStoreService`, relation sync from a single request, undeclared-field rejection at the service level |
| `UpdateServiceTest.php` | End-to-end update, nested relation sync on update |
| `DeleteServiceTest.php` | Successful delete + `RecordDeleted` dispatch, empty-targets 404 result, associated file cleanup |
| `BulkDeleteServiceTest.php` | Multi-ID delete, **Bug 4.5 regression** (scalar `ids` does not throw), missing-`ids` 404 result |
| `StatisticsServiceTest.php` | Zero-fill bucket behavior, cache TTL behavior (second create within cache window does not change cached result) |
| `MacrosTest.php` | All five macro classes: `Blueprint::status/standardTime`, `Carbon::parseOrNow`, `Str::snakeToTitle/humanText`, `Builder::customOrdering` (including **Security S2 regression** — unsafe column ignored), `Builder::datesFiltering`, `Response::success/error` |

### Key Regression Test — Bug 4.5

```php
// tests/Feature/BulkDeleteServiceTest.php
public function test_a_scalar_ids_value_does_not_throw_and_is_treated_as_a_single_id(): void
{
    $article = Article::create(['title' => 'Solo target']);

    $this->app->instance('request', Request::create('/articles', 'DELETE', [
        'ids' => (string) $article->id,   // scalar, not array
    ]));

    $service = $this->app->make(ArticleBulkDeleteService::class);
    $result = $service->delete();

    $this->assertTrue($result->isSuccessful());
}
```

### Key Regression Test — Security S2

```php
// tests/Feature/MacrosTest.php
public function test_builder_custom_ordering_ignores_an_unsafe_column(): void
{
    Article::create(['title' => 'Z']);

    $query = Article::query()->customOrdering('title; DROP TABLE articles', 'asc');

    $this->assertSame(1, $query->count());   // does not throw, ordering silently skipped
}
```

---

## Critical Pattern: Binding a Fake `Request`

**Correct:**

```php
$this->app->instance('request', Request::create('/articles', 'POST', [...]));
```

**Incorrect — does NOT work:**

```php
$this->app->bind(Request::class, fn () => Request::create('/articles', 'POST', [...]));
```

Laravel internally aliases `Illuminate\Http\Request` to the `'request'` string key in the container. Binding the class name directly does not intercept calls made via the `request()` helper or via container resolution that goes through the `'request'` alias (which is how Laravel macros like `Builder::customOrdering()` resolve the current request). Always use `$this->app->instance('request', ...)` when faking the request in a test — this exact mistake was caught and fixed across 5 test files during the package's own development.

---

## Writing Your Own Tests Against the Package

### Testing a Custom Crud Service

```php
use Nexus\CrudEngine\Tests\TestCase;
use Illuminate\Http\Request;

class PostStoreServiceTest extends TestCase
{
    public function test_it_creates_a_post(): void
    {
        $this->app->instance('request', Request::create('/posts', 'POST', [
            'title' => 'Hello',
        ]));

        $result = $this->app->make(PostStoreService::class)->store();

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(201, $result->code);
    }
}
```

### Testing Capability Checks in Isolation

```php
$registry = new CapabilityRegistry();
$this->assertTrue($registry->supportsFileUpload(new Post()));
```

### Faking File Storage

```php
use Illuminate\Support\Facades\Storage;

protected function setUp(): void
{
    parent::setUp();
    Storage::fake('testing');
}

public function test_file_is_stored(): void
{
    $file = UploadedFile::fake()->create('cover.jpg', 10);
    // ...
    Storage::disk('testing')->assertExists("posts/{$post->id}/{$fileName}");
}
```

### Faking Events

```php
use Illuminate\Support\Facades\Event;
use Nexus\CrudEngine\Events\RecordCreated;

Event::fake([RecordCreated::class]);
// ... perform the operation ...
Event::assertDispatched(RecordCreated::class);
```
