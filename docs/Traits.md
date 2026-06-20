# Traits

---

## `HasFileUrlsTrait`

**Namespace:** `Nexus\CrudEngine\Traits\HasFileUrlsTrait`

**Purpose:** Overrides `Model::toArray()` to rewrite file attribute values from stored filenames into full, disk-aware URLs. This ensures that every `toArray()` call (and therefore every JSON response built from an Eloquent model or resource) returns complete, publicly accessible URLs instead of raw filenames.

**Replaces:** The standalone `HandleToArrayTrait.php` uploaded as part of the original codebase.

---

### Usage

Apply the trait to any Eloquent model that also implements `FileUpload`:

```php
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Post extends Model implements FileUpload
{
    use HasFileUrlsTrait;

    public function documentFullPathStore(): string
    {
        return 'posts/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image', 'thumbnail'];
    }
}
```

After this, calling `$post->toArray()` — or serializing a collection to JSON — returns:

```json
{
  "id": 42,
  "title": "My Post",
  "cover_image": "https://your-bucket.s3.amazonaws.com/posts/42/a1b2c3d4.jpg",
  "thumbnail":   "https://your-bucket.s3.amazonaws.com/posts/42/b2c3d4e5.jpg"
}
```

Instead of the raw filenames stored in the database:

```json
{
  "cover_image": "a1b2c3d4.jpg",
  "thumbnail":   "b2c3d4e5.jpg"
}
```

---

### `toArray()` Behavior

```php
public function toArray(): array
{
    $data = parent::toArray();

    if (! $this instanceof FileUpload) {
        return $data;                    // trait is a no-op on non-FileUpload models
    }

    $files = app(FileLifecycleServiceInterface::class);

    foreach ($this->requestKeysForFile() as $attribute) {
        if (isset($data[$attribute]) && $data[$attribute] !== null) {
            $data[$attribute] = $files->url($this, $data[$attribute]);
        }
    }

    return $data;
}
```

- Attributes that are `null` or absent from `toArray()` output are left unchanged.
- `$files->url($this, $fileName)` calls `$disk->url(PathHelper::joinPath($directory, $fileName))` — the same disk configured in `crud-engine.files.disk`.

---

### The `app()` Call: Documented Exception

The implementation resolves `FileLifecycleServiceInterface` via `app()` inside `toArray()`. This is a deliberate, documented exception to the package's "no `app()` in business logic" rule:

> Eloquent models are instantiated by Eloquent itself (via `new`, hydration, or factories), never by the service container, so constructor injection is not possible for a trait applied to a Model.

Every other class in this package uses constructor injection exclusively. This is the only case where `app()` appears in production code, and it is isolated to a single line in this single trait.

---

### Common Mistakes

**Using the trait on a model that does not implement `FileUpload`.**

The trait guards against this (`if (! $this instanceof FileUpload) return $data`) so it is safe, but it is meaningless. The trait only rewrites attributes listed by `requestKeysForFile()`, which only exists on `FileUpload` models.

**Listing an attribute in `requestKeysForFile()` that is not in `$fillable`.**

If the attribute is not in `$fillable` (or is in `$hidden`), it will not appear in `parent::toArray()`, and the URL-rewrite step will be skipped silently. The database stores the filename but the API never returns the URL. Ensure every file attribute is fillable and not hidden.

**Calling `$model->cover_image` expecting a URL.**

`HasFileUrlsTrait` only rewrites the value inside `toArray()`. The model property itself still holds the raw filename. If you need the URL outside of JSON serialization, call `FileLifecycleServiceInterface::url($model, $model->cover_image)` directly.

**Applying the trait but not implementing `FileUpload`.**

The `FileUpload` interface is what `documentFullPathStore()` comes from. Without it, `ModelDefinedPathResolver::resolve()` will throw `FileOperationException`. Always implement the interface alongside the trait.

---

### Best Practices

- Apply the trait to the model, not to an API resource. If you use Eloquent API Resources (`JsonResource`), `toArray()` on the resource calls `$this->resource->toArray()` on the underlying model — the URL rewrite still applies.
- If you use `$hidden` to exclude file attributes from `toArray()` for security reasons, remove the attribute from `requestKeysForFile()` as well, otherwise the trait will perform a URL lookup that is then discarded.
