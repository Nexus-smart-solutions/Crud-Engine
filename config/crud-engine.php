<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------
    |
    | When true (the default for new installs, per your confirmation),
    | Nexus\CrudEngine\Services\Validation\LaravelRequestValidator returns
    | only the fields a request's rules() declares — fixing the original
    | codebase's Bug 4.1, where validation ran but `$request->all()` was
    | returned regardless, exposing every model to mass assignment via
    | undeclared fields.
    |
    */
    'strict_validation' => true,

    /*
    |--------------------------------------------------------------------
    | Capability Strictness
    |--------------------------------------------------------------------
    |
    | When true, syncing a relation or file key that the target model
    | does not declare support for throws
    | Nexus\CrudEngine\Exceptions\UnsupportedCapabilityException instead
    | of silently ignoring it. Defaults to false to preserve the original
    | codebase's behavior.
    |
    */
    'strict_capabilities' => false,

    /*
    |--------------------------------------------------------------------
    | Files
    |--------------------------------------------------------------------
    */
    'files' => [
        // Disk used by Nexus\CrudEngine\Services\Files\FileLifecycleService.
        // Defaults to the application's default filesystem disk.
        'disk' => env('CRUD_ENGINE_DISK', config('filesystems.default', 'local')),
    ],

    /*
    |--------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------
    */
    'relations' => [
        // Guards against runaway recursion when nested relations
        // reference each other. The original codebase had no such guard.
        'max_recursion_depth' => 5,
    ],

    /*
    |--------------------------------------------------------------------
    | Statistics
    |--------------------------------------------------------------------
    */
    'statistics' => [
        // 'eloquent' (default, zero extra dependencies, portable across
        // MySQL/Postgres/SQLite) or 'spatie' (requires
        // spatie/laravel-query-builder to be installed separately).
        'query_strategy' => env('CRUD_ENGINE_STATISTICS_STRATEGY', 'eloquent'),

        // Cache TTL, in seconds, for getStatistics() results.
        'cache_ttl' => env('CRUD_ENGINE_STATISTICS_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------
    | Response Formatting
    |--------------------------------------------------------------------
    */
    'response_formatter' => \Nexus\CrudEngine\Services\Responses\JsonResponseFormatter::class,

    /*
    |--------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------
    |
    | When true, Nexus\CrudEngine\Listeners\LogCrudOperationListener is
    | registered automatically and logs every Crud domain event,
    | replacing the original codebase's inline Log::info()/Log::error()
    | calls. Set to false if you only want your own listeners.
    |
    */
    'log_operations' => true,

];
