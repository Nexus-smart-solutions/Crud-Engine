# Installation

## Requirements

| Requirement | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^10.0 \| ^11.0 \| ^12.0 |
| nesbot/carbon | ^2.0 \| ^3.0 |

## Composer Installation

```bash
composer require nexus/crud-engine
```

The package registers itself automatically via Laravel's package auto-discovery. The service provider `Nexus\CrudEngine\Providers\CrudEngineServiceProvider` and the `CrudEngine` facade alias are discovered from the `extra.laravel` block in `composer.json` — no entry in `config/app.php` or `bootstrap/providers.php` is required.

## Publishing Assets

Run the install command to publish config and language files in one step and print a quick-start checklist:

```bash
php artisan crud-engine:install
```

This is equivalent to running both publish commands individually:

```bash
php artisan vendor:publish --tag=crud-engine-config
php artisan vendor:publish --tag=crud-engine-lang
```

**Config file** is published to `config/crud-engine.php`.

**Language files** are published to `lang/vendor/crud-engine/` with `en/` and `ar/` subdirectories, each containing `responses.php`.

Publishing is **optional**. If you don't publish, the package's own bundled defaults are used. Publish only when you need to override translation strings or change a config value that isn't covered by environment variables.

## Environment Variables

These `.env` keys are read by the published config file. Set them without publishing if you only need to change disk or caching settings:

| Variable | Config key | Default |
|---|---|---|
| `CRUD_ENGINE_DISK` | `files.disk` | value of `filesystems.default` |
| `CRUD_ENGINE_STATISTICS_STRATEGY` | `statistics.query_strategy` | `'eloquent'` |
| `CRUD_ENGINE_STATISTICS_CACHE_TTL` | `statistics.cache_ttl` | `300` |

## Optional Dependency: spatie/laravel-query-builder

If you want to use the Spatie-backed statistics query strategy, install it separately:

```bash
composer require spatie/laravel-query-builder
```

Then set `CRUD_ENGINE_STATISTICS_STRATEGY=spatie` in `.env`. The package **never** requires this package as a hard dependency — the container binding falls back to `EloquentAggregateStrategy` automatically when it is not installed.

## Verification

After installation, run:

```bash
php artisan about
```

The `CrudEngineServiceProvider` appears in the loaded providers list, and the five macro classes are registered during boot.

To verify macro registration, open a Tinker session:

```bash
php artisan tinker
>>> Str::snakeToTitle('hello_world')
=> "Hello World"
>>> Carbon\Carbon::parseOrNow('invalid')
=> Carbon\Carbon {...}  // returns now() instead of throwing
```
