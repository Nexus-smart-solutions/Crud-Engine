# Helpers

---

## `PathHelper`

**Namespace:** `Nexus\CrudEngine\Helpers\PathHelper`

**Purpose:** Pure filesystem path utility functions. Not instantiable — all methods are static.

The distinction between this class and the static classes the Phase 1 audit flagged as anti-patterns (`StoragePictures`, `HandleRelationHasMany`, etc.) is important: those static classes performed I/O and owned business logic; `PathHelper` methods are deterministic transformations of their input with no side effects, no I/O, and no state. They require no mocking in tests.

**Constructor:** Private — the class cannot be instantiated.

---

### `PathHelper::joinPath(string $directory, string $fileName): string`

**Purpose:** Joins a storage directory and a filename into a single path, normalizing surrounding slashes so there is always exactly one `/` between directory and filename and no leading/trailing `/` on the result.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$directory` | `string` | Storage directory, e.g. `'posts/42'` or `'/posts/42/'` |
| `$fileName` | `string` | Filename, e.g. `'cover.jpg'` or `'/cover.jpg'` |

**Returns:** `string` — the joined path without leading or trailing slashes.

**Implementation:**

```php
return trim($directory, '/').'/'.ltrim($fileName, '/');
```

**Examples:**

```php
PathHelper::joinPath('posts/42', 'cover.jpg');     // 'posts/42/cover.jpg'
PathHelper::joinPath('/posts/42/', '/cover.jpg');   // 'posts/42/cover.jpg'
PathHelper::joinPath('posts/42', '');              // 'posts/42/'  — empty filename edge case
```

**Used by:** `FileLifecycleService::delete()` and `FileLifecycleService::url()` to construct the disk path passed to `Storage::delete()` and `Storage::url()`.

---

### `PathHelper::normalizeDirectory(string $directory): string`

**Purpose:** Strips leading and trailing slashes from a directory string.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$directory` | `string` | A directory path, e.g. `'/posts/42/'` |

**Returns:** `string` — the directory without surrounding slashes.

**Implementation:**

```php
return trim($directory, '/');
```

**Examples:**

```php
PathHelper::normalizeDirectory('/posts/42/');   // 'posts/42'
PathHelper::normalizeDirectory('posts/42');     // 'posts/42'
PathHelper::normalizeDirectory('/');            // ''
```

**Not currently called internally** — available for use in custom `FilePathResolverInterface` implementations or application code that constructs storage paths.

---

## Using `PathHelper` in Custom Code

If you write a custom `FilePathResolverInterface` that assembles paths from multiple components, use `PathHelper` to avoid double-slash issues:

```php
class TenantAwarePathResolver implements FilePathResolverInterface
{
    public function resolve(Model $model): string
    {
        $tenant = PathHelper::normalizeDirectory((string) tenant()->id);
        $base   = PathHelper::normalizeDirectory($model->documentFullPathStore());

        return PathHelper::joinPath($tenant, $base);
        // e.g. 'tenant-7/posts/42'
    }
}
```

---

## Common Mistakes

**Expecting `joinPath` to validate that the path is safe.**

`PathHelper` is a pure string utility — it does not sanitize against path traversal. Sanitization of client-supplied filenames is the responsibility of `OriginalFilenameStrategy`. Never pass client-supplied values directly to `joinPath` without first running them through the naming strategy.

**Using `joinPath` to construct absolute filesystem paths.**

The package stores files via Laravel's `Storage` facade (disk abstraction). Paths passed to `Storage::disk('s3')->put(...)` are relative to the disk root, not to the server filesystem. `joinPath` produces relative paths — that is the correct input for `Storage` methods.
