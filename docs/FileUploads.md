# File Uploads

Complete guide to file lifecycle management: storing, deleting, and URL-rewriting file-backed model attributes.

---

## Architecture

```
Crud Service (Store/Update)
        │
        ├─ withoutFileFields()  — strips file attrs before mass-assignment
        ├─ DB::transaction(create/update)
        │
        ▼ (AFTER the transaction commits — Bug 4.6 fix)
handleFiles($model, $data)
        │
        ▼
FileLifecycleServiceInterface::applyIncomingValue($model, $attribute, $value)
        │
        ├─ UploadedFile → store()
        │       ├─ CapabilityRegistry::usesOriginalFilename($model)?
        │       │       ├─ true  → OriginalFilenameStrategy (sanitized)
        │       │       └─ false → HashedFilenameStrategy (default)
        │       ├─ Storage::disk($disk)->putFileAs($directory, $file, $fileName)
        │       ├─ $model->{$attribute} = $fileName; $model->save();
        │       └─ dispatch FileStored
        │
        └─ null → delete()
                ├─ Storage::disk($disk)->delete($directory/$fileName)
                ├─ $model->{$attribute} = null; $model->save();  ← Bug 4.2 fix
                └─ dispatch FileDeleted
```

---

## Step 1 — Implement `FileUpload` on Your Model

```php
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Post extends Model implements FileUpload
{
    use HasFileUrlsTrait;   // rewrites file attrs to URLs in toArray()

    protected $fillable = ['title', 'body', 'cover_image'];

    public function documentFullPathStore(): string
    {
        return 'posts/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image'];
    }
}
```

## Step 2 — Declare the Field in Your Request

```php
class PostStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:255'],
            'cover_image'  => ['nullable', 'file', 'image', 'max:5120'],
        ];
    }
}
```

## Step 3 — Use a Standard Crud Service (No Extra Code Needed)

```php
class PostStoreService extends AbstractStoreService
{
    public function model(): string { return Post::class; }
    public function requestFile(): string { return PostStoreRequest::class; }
}
```

File handling is **automatic** — `AbstractStoreService::store()` detects that `Post` implements `FileUpload`, strips `cover_image` from the initial `create()` call, persists the model, then stores the uploaded file and re-saves the attribute, all after the transaction commits.

```php
// Controller
public function store(Request $request, PostStoreService $service)
{
    $result = $service->store();
    return response()->json($result->toArray(), $result->code);
}
```

```bash
curl -X POST /api/posts \
  -F "title=My Post" \
  -F "cover_image=@/path/to/photo.jpg"
```

---

## Updating a File

Sending a new `UploadedFile` for `cover_image` on update **replaces** the existing file — the old file is not automatically deleted by the update path (only `applyIncomingValue` runs `store()`, which overwrites the model attribute; the prior physical file is orphaned unless you explicitly clear it first). To intentionally remove a file, send `null`:

```php
// PUT /api/posts/42
// Multipart body with cover_image omitted, or:
// JSON body: { "cover_image": null }
```

When `LaravelRequestValidator` validates `'cover_image' => ['nullable', ...]` and the client explicitly sends `null`, `applyIncomingValue()` routes to `delete()`, which removes the physical file and nulls the database column.

---

## Manual File Operations

Outside of a Crud service, inject `FileLifecycleServiceInterface` directly:

```php
class PostController extends Controller
{
    public function __construct(
        private readonly FileLifecycleServiceInterface $files,
    ) {}

    public function removeCover(Post $post)
    {
        $operation = $this->files->delete($post, 'cover_image');

        return response()->json([
            'deleted' => $operation->fileName,
        ]);
    }

    public function uploadCover(Request $request, Post $post)
    {
        $operation = $this->files->store($post, 'cover_image', $request->file('cover_image'));

        return response()->json([
            'url' => $operation->url,
        ]);
    }
}
```

---

## Getting a File URL Directly

```php
$url = app(FileLifecycleServiceInterface::class)->url($post, $post->cover_image);
```

Or, if the model uses `HasFileUrlsTrait`, simply call `toArray()`:

```php
$post->toArray()['cover_image'];  // already a full URL
```

---

## Original Filenames (Path-Traversal-Safe)

Implement `OriginalName` to preserve the client's original filename instead of a hashed one:

```php
use Nexus\CrudEngine\Contracts\Capabilities\OriginalName;

class LegalDocument extends Model implements FileUpload, OriginalName
{
    public function documentFullPathStore(): string
    {
        return 'documents/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['original_file'];
    }
}
```

`OriginalFilenameStrategy` sanitizes the filename before storing it — see [Strategies.md](Strategies.md#originalfilenamestrategy) for the full sanitization pipeline. A client filename like `../../../etc/passwd.jpg` is stored as `passwd.jpg`, never escaping the configured directory.

---

## Using S3 or Another Disk

Set the disk in `.env`:

```env
CRUD_ENGINE_DISK=s3
```

No code changes are required — `FileLifecycleService` reads the disk name from `crud-engine.files.disk` and delegates to `Illuminate\Contracts\Filesystem\Factory::disk($disk)`, which already understands every disk driver configured in `config/filesystems.php`.

---

## Multiple File Fields on One Model

```php
class Post extends Model implements FileUpload
{
    use HasFileUrlsTrait;

    public function documentFullPathStore(): string
    {
        return 'posts/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image', 'attachment', 'thumbnail'];
    }
}
```

All three fields are handled automatically — `AbstractStoreService::handleFiles()` iterates every key returned by `requestKeysForFile()` and calls `applyIncomingValue()` for each one present in the validated data.

---

## Nested Models with Files

A child model created via a `hasMany`/`hasOne` relation can also implement `FileUpload` — `HasManySyncStrategy` and `HasOneSyncStrategy` both call `handleChildFiles()` automatically. See [Relations.md](Relations.md#nested-file-uploads) for a full example.

---

## Common Mistakes

**Forgetting `'nullable'` in the request rules for a file field.**

If `cover_image` is `'required'` on every update, clients can never send `null` to remove the file — the validator rejects the request before `FileLifecycleService` is reached.

**Storing files inside the DB transaction.**

This is already handled correctly by the package — file I/O happens after the transaction commits (Bug 4.6 fix) — but if you call `FileLifecycleServiceInterface::store()` manually inside your own `DB::transaction()` closure, you reintroduce the exact problem the package fixed: a rollback after a successful disk write leaves an orphaned file.

**Assuming `delete()` is safe to call when no file exists.**

`FileLifecycleService::delete()` checks `if ($currentFileName)` before attempting a disk delete, so calling it on an already-null attribute is a safe no-op that still nulls and saves the attribute (which is already null) and dispatches `FileDeleted` with `fileName: null`.

**Not implementing `requestKeysForFile()` consistently with `$fillable`.**

If a file attribute is in `requestKeysForFile()` but not in the model's `$fillable` array, `withoutFileFields()` still strips it before `create()`/`update()` (this is independent of `$fillable`), but `parent::toArray()` inside `HasFileUrlsTrait` will not include it, so the URL rewrite is silently skipped.
