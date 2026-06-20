# API Reference

Complete, condensed reference for every public class, interface, and configuration option in `nexus/crud-engine`. For narrative explanations, usage patterns, and examples, see the linked topic guide for each section.

---

## Contracts

Full guide: [Contracts.md](Contracts.md)

### Capabilities (`Nexus\CrudEngine\Contracts\Capabilities\`)

| Interface | Methods |
|---|---|
| `FileUpload` | `documentFullPathStore(): string` · `requestKeysForFile(): array` |
| `HasManyRelations` | `getHasManyRelations(): array` |
| `HasOneRelations` | `getHasOneRelations(): array` |
| `ManyToManyRelations` | `getManyToManyRelations(): array` |
| `OriginalName` | *(marker — no methods)* |

### Core (`Nexus\CrudEngine\Contracts\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `CapabilityRegistryInterface` | `supportsFileUpload(object): bool` · `supportsHasMany(object): bool` · `supportsHasOne(object): bool` · `supportsManyToMany(object): bool` · `usesOriginalFilename(object): bool` | `CapabilityRegistry` (singleton) |

### Repositories (`Nexus\CrudEngine\Contracts\Repositories\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `RepositoryInterface` | `modelClass(): string` · `create(array): Model` · `update(Model, array): Model` · `delete(Model): bool` · `find(int\|string): ?Model` · `findManyByIds(array): Collection` | `EloquentRepository` (via `RepositoryFactory`) |

### Files (`Nexus\CrudEngine\Contracts\Files\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `FileLifecycleServiceInterface` | `store(Model, string, UploadedFile): FileOperation` · `delete(Model, string): FileOperation` · `url(Model, string): string` · `applyIncomingValue(Model, string, mixed): ?FileOperation` | `FileLifecycleService` (bind) |
| `FileNamingStrategyInterface` | `generateName(UploadedFile, Model): string` | `HashedFilenameStrategy` / `OriginalFilenameStrategy` |
| `FilePathResolverInterface` | `resolve(Model): string` | `ModelDefinedPathResolver` (bind) |

### Relations (`Nexus\CrudEngine\Contracts\Relations\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `RelationSyncManagerInterface` | `syncAll(Model, array, int $depth = 0): void` | `RelationSyncManager` (singleton) |
| `RelationSyncStrategyInterface` | `sync(RelationSyncContext): void` | `HasManySyncStrategy` / `HasOneSyncStrategy` / `ManyToManySyncStrategy` |

### Responses (`Nexus\CrudEngine\Contracts\Responses\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `ResponseFormatterInterface` | `format(CrudOperationResult): JsonResponse` · `translate(string): string` | `JsonResponseFormatter` (bind, config-swappable) |

### Validation (`Nexus\CrudEngine\Contracts\Validation\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `RequestValidatorInterface` | `validate(string $requestClass): array` — *throws `CrudValidationException`* | `LaravelRequestValidator` (bind) |

### Statistics (`Nexus\CrudEngine\Contracts\Statistics\`)

| Interface | Methods | Default Binding |
|---|---|---|
| `StatisticsQueryStrategyInterface` | `execute(StatisticsQuery): array` | `EloquentAggregateStrategy` / `SpatieQueryBuilderStrategy` |

### Services (`Nexus\CrudEngine\Contracts\Services\`)

| Interface | Methods |
|---|---|
| `CreatesRecords` | `store(): CrudOperationResult` |
| `UpdatesRecords` | `update(): CrudOperationResult` |
| `DeletesRecords` | `delete(): CrudOperationResult` |

---

## DTOs

Full guide: [DTOs.md](DTOs.md)

| Class | Type | Properties |
|---|---|---|
| `CrudOperationResult` | `final readonly` | `status: OperationStatus` · `messages: string[]` · `data: array` · `code: int` · `failedIds: array` · `meta: array` — static factories `success()`, `partialSuccess()`, `error()`; methods `isSuccessful()`, `toArray()` |
| `StoreContext` | `final readonly` | `modelClass: string` · `attributes: array` — method `withAttributes(array): self` |
| `UpdateContext` | `final readonly` | `model: Model` · `attributes: array` |
| `DeleteCriteria` | `final readonly` | `modelClass: string` · `ids: array` |
| `FileOperation` | `final readonly` | `type: FileOperationType` · `attribute: string` · `fileName: ?string` · `url: ?string` |
| `RelationSyncContext` | `final readonly` | `model: Model` · `relationName: string` · `incomingData: mixed` · `type: RelationType` · `depth: int = 0` |
| `StatisticsQuery` | `final readonly` | `modelClass: string` · `dateColumn: string` · `sumColumn: ?string` · `startDate: string` · `endDate: string` · `interval: string` · `scopes: array` · `allowedFilters: array` |

### Enums (`Nexus\CrudEngine\DTOs\Enums\`)

| Enum | Cases |
|---|---|
| `OperationStatus` | `Success` = `'success'` · `PartialSuccess` = `'partial_success'` · `Error` = `'error'` |
| `FileOperationType` | `Stored` = `'stored'` · `Deleted` = `'deleted'` · `Skipped` = `'skipped'` |
| `RelationType` | `HasMany` = `'has_many'` · `HasOne` = `'has_one'` · `ManyToMany` = `'many_to_many'` |

---

## Services

Full guides: [CRUD-Services.md](CRUD-Services.md) · [Services.md](Services.md) · [Statistics.md](Statistics.md)

### Crud Services (`Nexus\CrudEngine\Services\Crud\`)

| Class | Implements | Abstract methods you implement |
|---|---|---|
| `AbstractStoreService` | `CreatesRecords` | `model(): string` · `requestFile(): string` |
| `AbstractUpdateService` | `UpdatesRecords` | `model(): string` · `requestFile(): string` · `resolveModel(): Model` |
| `AbstractDeleteService` | `DeletesRecords` | `model(): string` · `resolveTargets(): Collection` |
| `AbstractBulkDeleteService` (extends `AbstractDeleteService`) | `DeletesRecords` | `model(): string` *(resolveTargets() pre-implemented)* |

### Other Concrete Services

| Class | Namespace | Implements |
|---|---|---|
| `CapabilityRegistry` | `Services\Capabilities\` | `CapabilityRegistryInterface` |
| `FileLifecycleService` | `Services\Files\` | `FileLifecycleServiceInterface` |
| `ModelDefinedPathResolver` | `Services\Files\` | `FilePathResolverInterface` |
| `RelationSyncManager` | `Services\Relations\` | `RelationSyncManagerInterface` |
| `LaravelRequestValidator` | `Services\Validation\` | `RequestValidatorInterface` |
| `JsonResponseFormatter` | `Services\Responses\` | `ResponseFormatterInterface` |
| `AbstractStatisticsService` | `Services\Statistics\` | *(abstract base, no interface)* — implement `getModelClass()`, `getDateColumn()`; public method `getStatistics(string, string, string): array` |

---

## Strategies

Full guide: [Strategies.md](Strategies.md)

| Class | Namespace | Implements | Selected when |
|---|---|---|---|
| `HashedFilenameStrategy` | `Strategies\Files\` | `FileNamingStrategyInterface` | Model does not implement `OriginalName` (default) |
| `OriginalFilenameStrategy` | `Strategies\Files\` | `FileNamingStrategyInterface` | Model implements `OriginalName` |
| `HasManySyncStrategy` | `Strategies\Relations\` | `RelationSyncStrategyInterface` | Relation type is `RelationType::HasMany` |
| `HasOneSyncStrategy` | `Strategies\Relations\` | `RelationSyncStrategyInterface` | Relation type is `RelationType::HasOne` |
| `ManyToManySyncStrategy` | `Strategies\Relations\` | `RelationSyncStrategyInterface` | Relation type is `RelationType::ManyToMany` |
| `EloquentAggregateStrategy` | `Strategies\Statistics\` | `StatisticsQueryStrategyInterface` | `crud-engine.statistics.query_strategy = 'eloquent'` (default) |
| `SpatieQueryBuilderStrategy` | `Strategies\Statistics\` | `StatisticsQueryStrategyInterface` | `crud-engine.statistics.query_strategy = 'spatie'` AND package installed |

---

## Repositories

Full guide: [Repositories.md](Repositories.md)

| Class | Namespace | Notes |
|---|---|---|
| `EloquentRepository` | `Repositories\` | Implements `RepositoryInterface`; constructed with one `class-string<Model>` argument |
| `RepositoryFactory` | `Repositories\` | `make(string $modelClass): RepositoryInterface` — singleton, builds a new `EloquentRepository` per call |

---

## Events

Full guide: [Events.md](Events.md)

All events live under `Nexus\CrudEngine\Events\`, are `final`, and have `readonly` public properties.

| Event | Properties | Dispatched by |
|---|---|---|
| `RecordCreated` | `model: Model` · `context: StoreContext` | `AbstractStoreService::store()` |
| `RecordUpdated` | `model: Model` · `context: UpdateContext` | `AbstractUpdateService::update()` |
| `RecordDeleted` | `model: Model` | `AbstractDeleteService::delete()` per success |
| `RecordDeletionFailed` | `model: Model` · `exception: \Throwable` | `AbstractDeleteService::delete()` per failure |
| `FileStored` | `model: Model` · `operation: FileOperation` | `FileLifecycleService::store()` |
| `FileDeleted` | `model: Model` · `operation: FileOperation` | `FileLifecycleService::delete()` |
| `RelationSynced` | `model: Model` · `relationName: string` · `type: RelationType` | `RelationSyncManager::dispatchEach()` |

---

## Listeners

Full guide: [Listeners.md](Listeners.md)

| Class | Namespace | Subscribes to | Config gate |
|---|---|---|---|
| `LogCrudOperationListener` | `Listeners\` | All 7 events above | `crud-engine.log_operations` (default `true`) |

Constructor: `__construct(LoggerInterface $logger)`. Method: `subscribe(Dispatcher $events): void`.

---

## Traits

Full guide: [Traits.md](Traits.md)

| Trait | Namespace | Purpose |
|---|---|---|
| `HasFileUrlsTrait` | `Traits\` | Overrides `Model::toArray()` to rewrite `requestKeysForFile()` attributes into full URLs via `FileLifecycleServiceInterface::url()`. Apply to any model implementing `FileUpload`. |

---

## Helpers

Full guide: [Helpers.md](Helpers.md)

| Class | Namespace | Methods |
|---|---|---|
| `PathHelper` | `Helpers\` | `static joinPath(string $directory, string $fileName): string` · `static normalizeDirectory(string $directory): string` — not instantiable |

---

## Macros

Full guide: [Macros.md](Macros.md)

All registered automatically by `CrudEngineServiceProvider::boot()`.

| Macro Class | Namespace | Registers |
|---|---|---|
| `BlueprintMacros` | `Macros\` | `Blueprint::status(int $default = 1)` · `Blueprint::standardTime()` |
| `BuilderMacros` | `Macros\` | `Builder::datesFiltering(string $column = 'created_at')` · `Builder::customOrdering(?string $sortColumn = null, ?string $sort = null)` |
| `CarbonMacros` | `Macros\` | `Carbon::parseOrNow(mixed $date = ''): Carbon` |
| `ResponseMacros` | `Macros\` | `Response::success(array $data = [], array $messages = [], int $code = 200): JsonResponse` · `Response::error(string\|array $messages = '', int $code = 500): JsonResponse` |
| `StrMacros` | `Macros\` | `Str::snakeToTitle(string $value): string` · `Str::humanText(string $value): string` |

---

## Facades

Full guide: [Facades.md](Facades.md)

| Facade | Namespace | Accessor | Proxies |
|---|---|---|---|
| `CrudEngine` | `Facades\` | `CapabilityRegistryInterface::class` | All 5 `CapabilityRegistryInterface` methods |

---

## Exceptions

Not separately documented as a topic guide — referenced throughout. All extend `Nexus\CrudEngine\Exceptions\CrudEngineException` (itself extends `\RuntimeException`).

| Exception | Namespace | Thrown by |
|---|---|---|
| `CrudEngineException` | `Exceptions\` | Base class for all package exceptions |
| `CrudValidationException` | `Exceptions\` | `LaravelRequestValidator::validate()` on validation failure. Implements `Responsable` — self-renders the error envelope via `ResponseFormatterInterface`. Methods: `errors(): array`, `validator(): Validator` |
| `FileOperationException` | `Exceptions\` | `FileLifecycleService::store()/delete()` on I/O failure; `ModelDefinedPathResolver::resolve()` if model lacks `FileUpload`. Static factories: `storeFailed()`, `deleteFailed()` |
| `RelationSyncException` | `Exceptions\` | `HasManySyncStrategy`/`HasOneSyncStrategy`/`ManyToManySyncStrategy` on missing relation method or max recursion depth exceeded. Static factories: `relationMethodMissing()`, `maxRecursionDepthExceeded()` |
| `UnsupportedCapabilityException` | `Exceptions\` | `RelationSyncManager::dispatchEach()` when `strict_capabilities = true` and a declared relation is absent from incoming data. Static factories: `forRelation()`, `forFileAttribute()` |

---

## Configuration

Full guide: [Configuration.md](Configuration.md)

Published to `config/crud-engine.php` via `php artisan crud-engine:install`.

| Key | Type | Default | Env Variable |
|---|---|---|---|
| `strict_validation` | `bool` | `true` | — |
| `strict_capabilities` | `bool` | `false` | — |
| `files.disk` | `string` | `config('filesystems.default')` | `CRUD_ENGINE_DISK` |
| `relations.max_recursion_depth` | `int` | `5` | — |
| `statistics.query_strategy` | `string` | `'eloquent'` | `CRUD_ENGINE_STATISTICS_STRATEGY` |
| `statistics.cache_ttl` | `int` (seconds) | `300` | `CRUD_ENGINE_STATISTICS_CACHE_TTL` |
| `response_formatter` | `class-string` | `JsonResponseFormatter::class` | — |
| `log_operations` | `bool` | `true` | — |

---

## Console Commands

| Command | Class | Action |
|---|---|---|
| `crud-engine:install` | `Console\InstallCommand` | Publishes `crud-engine-config` and `crud-engine-lang` tags, prints a quick-start checklist |

---

## Translation Namespace

Package translations are namespaced `crud-engine::` and ship in `en` and `ar`. Published to `lang/vendor/crud-engine/{locale}/responses.php`.

| Key | English default |
|---|---|
| `crud-engine::responses.success.created` | "The record was created successfully." |
| `crud-engine::responses.success.updated` | "The record was updated successfully." |
| `crud-engine::responses.success.deleted` | "The record was deleted successfully." |
| `crud-engine::responses.success.operation_completed` | "The operation completed successfully." |
| `crud-engine::responses.error.create_failed` | "The record could not be created." |
| `crud-engine::responses.error.update_failed` | "The record could not be updated." |
| `crud-engine::responses.error.delete_failed` | "The record could not be deleted." |
| `crud-engine::responses.error.partial_delete` | "Some records were deleted, but others could not be." |
| `crud-engine::responses.error.server_error` | "Something went wrong. Please try again." |
| `crud-engine::responses.error.validation_failed` | "The given data was invalid." |

---

## Service Provider Bindings Summary

All bindings registered in `Nexus\CrudEngine\Providers\CrudEngineServiceProvider::register()`:

| Contract | Implementation | Binding Type |
|---|---|---|
| `CapabilityRegistryInterface` | `CapabilityRegistry` | `singleton` |
| `FilePathResolverInterface` | `ModelDefinedPathResolver` | `bind` |
| `FileLifecycleServiceInterface` | `FileLifecycleService` | `bind` (factory closure) |
| `RelationSyncManagerInterface` | `RelationSyncManager` | `singleton` (factory closure) |
| `RequestValidatorInterface` | `LaravelRequestValidator` | `bind` |
| `ResponseFormatterInterface` | configured class (default `JsonResponseFormatter`) | `bind` (factory closure) |
| `StatisticsQueryStrategyInterface` | `EloquentAggregateStrategy` or `SpatieQueryBuilderStrategy` | `bind` (factory closure, guarded `class_exists()` check) |
| `RepositoryFactory` | `RepositoryFactory` | `singleton` |
| `HasManySyncStrategy` | — | `bind` (factory closure, `Container` injected for circular-dependency resolution) |
| `HasOneSyncStrategy` | — | `bind` (factory closure, `Container` injected for circular-dependency resolution) |
| `ManyToManySyncStrategy` | — | `bind` |

Macros and `LogCrudOperationListener` are registered in `boot()`, not `register()`.

---

## Cross-Reference Index

| If you need to... | See |
|---|---|
| Install the package | [Installation.md](Installation.md) |
| Change a config value | [Configuration.md](Configuration.md) |
| Build a create/update/delete endpoint | [CRUD-Services.md](CRUD-Services.md) |
| Handle file uploads on a model | [FileUploads.md](FileUploads.md) |
| Sync nested relations | [Relations.md](Relations.md) |
| Build a statistics dashboard | [Statistics.md](Statistics.md) |
| Use a Schema/Builder/Carbon/Str/Response macro | [Macros.md](Macros.md) |
| React to a Crud lifecycle event | [Events.md](Events.md) · [Listeners.md](Listeners.md) |
| Write tests against the package | [Testing.md](Testing.md) |
| Migrate from the old `App\Core` codebase | [Legacy-Migration.md](Legacy-Migration.md) |
| See a complete worked example | [Examples.md](Examples.md) |
