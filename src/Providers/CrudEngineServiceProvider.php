<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Providers;

use Illuminate\Support\ServiceProvider;
use Nexus\CrudEngine\Console\InstallCommand;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Files\FileNamingStrategyInterface;
use Nexus\CrudEngine\Contracts\Files\FilePathResolverInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\Contracts\Statistics\StatisticsQueryStrategyInterface;
use Nexus\CrudEngine\Contracts\Validation\RequestValidatorInterface;
use Nexus\CrudEngine\DTOs\Enums\RelationType;
use Nexus\CrudEngine\Listeners\LogCrudOperationListener;
use Nexus\CrudEngine\Macros\BlueprintMacros;
use Nexus\CrudEngine\Macros\BuilderMacros;
use Nexus\CrudEngine\Macros\CarbonMacros;
use Nexus\CrudEngine\Macros\ResponseMacros;
use Nexus\CrudEngine\Macros\StrMacros;
use Nexus\CrudEngine\Repositories\RepositoryFactory;
use Nexus\CrudEngine\Services\Capabilities\CapabilityRegistry;
use Nexus\CrudEngine\Services\Files\FileLifecycleService;
use Nexus\CrudEngine\Services\Files\ModelDefinedPathResolver;
use Nexus\CrudEngine\Services\Relations\RelationSyncManager;
use Nexus\CrudEngine\Services\Responses\JsonResponseFormatter;
use Nexus\CrudEngine\Services\Validation\LaravelRequestValidator;
use Nexus\CrudEngine\Strategies\Files\HashedFilenameStrategy;
use Nexus\CrudEngine\Strategies\Files\OriginalFilenameStrategy;
use Nexus\CrudEngine\Strategies\Relations\HasManySyncStrategy;
use Nexus\CrudEngine\Strategies\Relations\HasOneSyncStrategy;
use Nexus\CrudEngine\Strategies\Relations\ManyToManySyncStrategy;
use Nexus\CrudEngine\Strategies\Statistics\EloquentAggregateStrategy;
use Nexus\CrudEngine\Strategies\Statistics\SpatieQueryBuilderStrategy;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Wires every binding described in the Phase 2 architecture's "Service
 * Container Bindings" table. Also registers the five macro classes
 * (replacing the manual application-level registration the original
 * macros relied on) and the default logging listener.
 *
 * Discovered automatically via Composer (`extra.laravel.providers` in
 * composer.json) — no manual registration required in a consuming
 * application's `config/app.php` / `bootstrap/providers.php`.
 */
final class CrudEngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/crud-engine.php', 'crud-engine');

        $this->registerCapabilityRegistry();
        $this->registerFileServices();
        $this->registerRelationServices();
        $this->registerValidationService();
        $this->registerResponseFormatter();
        $this->registerStatisticsStrategy();
        $this->registerRepositoryFactory();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'crud-engine');

        $this->publishes([
            __DIR__.'/../../config/crud-engine.php' => $this->app->configPath('crud-engine.php'),
        ], 'crud-engine-config');

        $this->publishes([
            __DIR__.'/../../resources/lang' => $this->app->langPath('vendor/crud-engine'),
        ], 'crud-engine-lang');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        $this->registerMacros();
        $this->registerDefaultLogging();
    }

    /**
     * CapabilityRegistryInterface — singleton: stateless, pure
     * `instanceof` logic, safe to share across the application lifetime.
     */
    private function registerCapabilityRegistry(): void
    {
        $this->app->singleton(CapabilityRegistryInterface::class, CapabilityRegistry::class);
    }

    /**
     * File-related bindings. FileLifecycleServiceInterface and
     * FilePathResolverInterface are bound with `bind` (not `singleton`)
     * because the disk and path-resolution logic may be tenant- or
     * request-sensitive — see the Phase 2 Risk Analysis on multi-tenancy.
     */
    private function registerFileServices(): void
    {
        $this->app->bind(FilePathResolverInterface::class, ModelDefinedPathResolver::class);

        $this->app->bind(HashedFilenameStrategy::class);
        $this->app->bind(OriginalFilenameStrategy::class);

        $this->app->bind(FileLifecycleServiceInterface::class, function ($app) {
            return new FileLifecycleService(
                $app->make(\Illuminate\Contracts\Filesystem\Factory::class),
                $app->make(CapabilityRegistryInterface::class),
                $app->make(FilePathResolverInterface::class),
                $app->make(HashedFilenameStrategy::class),
                $app->make(OriginalFilenameStrategy::class),
                $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
                $app->make('config')->get('crud-engine.files.disk'),
            );
        });
    }

    /**
     * Relation sync bindings. RelationSyncManager is a singleton (it
     * holds no per-request state); each per-type strategy is resolved
     * fresh because they themselves depend on the request-sensitive
     * FileLifecycleServiceInterface.
     */
    private function registerRelationServices(): void
    {
        $maxDepth = (int) config('crud-engine.relations.max_recursion_depth', 5);

        $this->app->bind(HasManySyncStrategy::class, function ($app) use ($maxDepth) {
            return new HasManySyncStrategy(
                $app->make(CapabilityRegistryInterface::class),
                $app->make(FileLifecycleServiceInterface::class),
                $app,
                $maxDepth,
            );
        });

        $this->app->bind(HasOneSyncStrategy::class, function ($app) use ($maxDepth) {
            return new HasOneSyncStrategy(
                $app->make(CapabilityRegistryInterface::class),
                $app->make(FileLifecycleServiceInterface::class),
                $app,
                $maxDepth,
            );
        });

        $this->app->bind(ManyToManySyncStrategy::class);

        $this->app->singleton(RelationSyncManagerInterface::class, function ($app) {
            return new RelationSyncManager(
                $app->make(CapabilityRegistryInterface::class),
                [
                    RelationType::HasMany->value => $app->make(HasManySyncStrategy::class),
                    RelationType::HasOne->value => $app->make(HasOneSyncStrategy::class),
                    RelationType::ManyToMany->value => $app->make(ManyToManySyncStrategy::class),
                ],
                (bool) config('crud-engine.strict_capabilities', false),
                $app->make(\Illuminate\Contracts\Events\Dispatcher::class),
            );
        });
    }

    /**
     * RequestValidatorInterface — bound per resolution since it depends
     * on the current Request, which Laravel itself only binds for the
     * duration of one HTTP lifecycle.
     */
    private function registerValidationService(): void
    {
        $this->app->bind(RequestValidatorInterface::class, LaravelRequestValidator::class);
    }

    /**
     * ResponseFormatterInterface — config-swappable, per the Phase 2
     * binding map, so an application can override the envelope shape
     * (e.g. JSON:API) without touching any Crud service.
     */
    private function registerResponseFormatter(): void
    {
        $this->app->bind(ResponseFormatterInterface::class, function ($app) {
            $formatterClass = $app->make('config')->get('crud-engine.response_formatter', JsonResponseFormatter::class);

            return $app->make($formatterClass);
        });
    }

    /**
     * StatisticsQueryStrategyInterface — chosen once at boot via a
     * guarded class_exists() check, so spatie/laravel-query-builder
     * stays a Composer `suggest`, never a `require`, of this package
     * (per your clarification #7).
     */
    private function registerStatisticsStrategy(): void
    {
        $this->app->bind(StatisticsQueryStrategyInterface::class, function ($app) {
            $configured = $app->make('config')->get('crud-engine.statistics.query_strategy', 'eloquent');

            if ($configured === 'spatie' && class_exists(QueryBuilder::class)) {
                return $app->make(SpatieQueryBuilderStrategy::class);
            }

            return $app->make(EloquentAggregateStrategy::class);
        });
    }

    /**
     * RepositoryFactory — singleton: make() is cheap and stateless, even
     * though each repository it produces is request-scoped data access.
     */
    private function registerRepositoryFactory(): void
    {
        $this->app->singleton(RepositoryFactory::class);
    }

    private function registerMacros(): void
    {
        BlueprintMacros::register();
        BuilderMacros::register();
        CarbonMacros::register();
        ResponseMacros::register();
        StrMacros::register();
    }

    private function registerDefaultLogging(): void
    {
        if (! (bool) config('crud-engine.log_operations', true)) {
            return;
        }

        $this->app->make(LogCrudOperationListener::class)->subscribe(
            $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class)
        );
    }
}
