# Changelog

All notable changes to `nexus/crud-engine` will be documented in this file.

## [1.0.0] — Unreleased

Initial release: extraction and refactor of an internal CRUD/file/relation framework into a standalone, installable Laravel package.

### Added
- Generic Crud services (`AbstractStoreService`, `AbstractUpdateService`, `AbstractDeleteService`, `AbstractBulkDeleteService`) built on dependency injection, replacing the original static-call-based abstract classes.
- File lifecycle management (`FileLifecycleServiceInterface`) with swappable naming (`HashedFilenameStrategy`, `OriginalFilenameStrategy`) and path-resolution (`FilePathResolverInterface`) strategies.
- Nested relation synchronization (`RelationSyncManagerInterface`) with per-type strategies for hasMany, hasOne, and many-to-many relations.
- A `CapabilityRegistryInterface`, replacing six independently duplicated `instanceof` checks from the original codebase with a single source of truth.
- A statistics engine (`AbstractStatisticsService`) with a swappable query strategy: `EloquentAggregateStrategy` (default, zero extra dependencies, portable across MySQL/Postgres/SQLite) or `SpatieQueryBuilderStrategy` (optional, only if `spatie/laravel-query-builder` is installed).
- Five Laravel macros (`Blueprint::status()/standardTime()`, `Builder::datesFiltering()/customOrdering()`, `Carbon::parseOrNow()`, `Str::snakeToTitle()/humanText()`, `Response::success()/error()`), auto-registered by the service provider.
- Domain events (`RecordCreated`, `RecordUpdated`, `RecordDeleted`, `RecordDeletionFailed`, `FileStored`, `FileDeleted`, `RelationSynced`) with a default logging listener, replacing inline `Log::` calls.
- English and Arabic translation defaults under the package's own `crud-engine::` namespace.
- Full unit and feature test suite, including regression tests for every bug fixed below.

### Fixed (carried over from the Phase 1 audit of the original codebase)
- **Mass-assignment / validation bypass**: validation previously ran but `$request->all()` was returned regardless of which fields passed validation. `RequestValidatorInterface::validate()` now returns only `$validator->validated()`. Controlled by `crud-engine.strict_validation` (defaults to `true`).
- **Orphaned file references**: clearing a file attribute previously deleted the physical file but left the database column pointing at it. `FileLifecycleServiceInterface::delete()` now nulls and saves the attribute as part of the same operation.
- **HasOne/HasMany recursion mix-up**: the original nested-relation recursion called `getHasManyRelations()` inside the "has one" handler. Recursion now goes through the `CapabilityRegistry`, which cannot make this mistake by construction.
- **Bulk delete TypeError on scalar `ids` input**: a non-array `ids` value no longer throws; it's normalized into a single-element array.
- **Path traversal in original-filename mode**: client-supplied original filenames are now sanitized (directory components stripped, control characters and disallowed characters removed) before being used as a storage path.
- **Silent failure swallowing**: failed deletes and malformed sort requests now dispatch an event / log a warning instead of failing invisibly.
- **File writes inside a database transaction**: file storage now happens after the transaction commits, so a rolled-back transaction can never leave an orphaned file on disk.
- **Hardcoded MySQL-only statistics SQL**: the default statistics strategy is pure Eloquent and portable across MySQL, Postgres, and SQLite.

### Excluded
- `PaymentManagerInterface` was not migrated — it had no implementation or consumer in the original codebase and is out of scope for this package.
