# Examples — Full Worked `Post` Resource

A complete, end-to-end example: migration, model, requests, services, controller, routes, and sample requests/responses. Every piece shown here uses only real package APIs documented elsewhere in `docs/`.

---

## 1. Migration

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('cover_image')->nullable();
            $table->status();          // Blueprint macro: tinyInteger('status')->default(1)
            $table->standardTime();    // Blueprint macro: created_at, updated_at, soft deletes
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->string('attachment')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('post_tag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('posts');
    }
};
```

---

## 2. Model

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;
use Nexus\CrudEngine\Contracts\Capabilities\ManyToManyRelations;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Post extends Model implements FileUpload, HasManyRelations, ManyToManyRelations
{
    use HasFileUrlsTrait;

    protected $fillable = ['title', 'body', 'cover_image', 'status'];

    public function documentFullPathStore(): string
    {
        return 'posts/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image'];
    }

    public function getHasManyRelations(): array
    {
        return ['comments'];
    }

    public function getManyToManyRelations(): array
    {
        return ['tags'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
```

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Comment extends Model implements FileUpload
{
    use HasFileUrlsTrait;

    protected $fillable = ['body', 'attachment'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function documentFullPathStore(): string
    {
        return 'comments/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['attachment'];
    }
}
```

---

## 3. Form Requests

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['required', 'string', 'max:255'],
            'body'              => ['nullable', 'string'],
            'cover_image'       => ['nullable', 'file', 'image', 'max:5120'],
            'comments'          => ['nullable', 'array'],
            'comments.*.body'   => ['required_with:comments', 'string'],
            'tags'              => ['nullable', 'array'],
            'tags.*'            => ['integer', 'exists:tags,id'],
        ];
    }
}
```

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'             => ['sometimes', 'required', 'string', 'max:255'],
            'body'              => ['nullable', 'string'],
            'cover_image'       => ['nullable'],   // null clears the file; file replaces it
            'comments'          => ['nullable', 'array'],
            'comments.*.id'     => ['nullable', 'integer', 'exists:comments,id'],
            'comments.*.body'   => ['required_with:comments', 'string'],
            'tags'              => ['nullable', 'array'],
            'tags.*'            => ['integer', 'exists:tags,id'],
        ];
    }
}
```

---

## 4. Crud Services

```php
namespace App\Services\Posts;

use App\Http\Requests\PostStoreRequest;
use App\Models\Post;
use Nexus\CrudEngine\Services\Crud\AbstractStoreService;

class PostStoreService extends AbstractStoreService
{
    public function model(): string
    {
        return Post::class;
    }

    public function requestFile(): string
    {
        return PostStoreRequest::class;
    }

    protected function beforePersist(array $data): array
    {
        $data['status'] = $data['status'] ?? 1;
        return $data;
    }
}
```

```php
namespace App\Services\Posts;

use App\Http\Requests\PostUpdateRequest;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Services\Crud\AbstractUpdateService;

class PostUpdateService extends AbstractUpdateService
{
    private ?Post $post = null;

    public function forPost(Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function model(): string
    {
        return Post::class;
    }

    public function requestFile(): string
    {
        return PostUpdateRequest::class;
    }

    public function resolveModel(): Model
    {
        return $this->post ?? throw new \LogicException('Call forPost() before update().');
    }
}
```

```php
namespace App\Services\Posts;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;
use Nexus\CrudEngine\Services\Crud\AbstractDeleteService;

class PostDeleteService extends AbstractDeleteService
{
    private ?Collection $posts = null;

    public function forPosts(Collection $posts): static
    {
        $this->posts = $posts;
        return $this;
    }

    public function model(): string
    {
        return Post::class;
    }

    public function resolveTargets(): Collection
    {
        return $this->posts ?? new Collection();
    }
}
```

```php
namespace App\Services\Posts;

use App\Models\Post;
use Nexus\CrudEngine\Services\Crud\AbstractBulkDeleteService;

class PostBulkDeleteService extends AbstractBulkDeleteService
{
    public function model(): string
    {
        return Post::class;
    }
}
```

```php
namespace App\Services\Posts;

use App\Models\Post;
use Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService;

class PostStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string
    {
        return Post::class;
    }

    public function getDateColumn(): string
    {
        return 'created_at';
    }
}
```

---

## 5. Controller

```php
namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\Posts\PostBulkDeleteService;
use App\Services\Posts\PostDeleteService;
use App\Services\Posts\PostStatisticsService;
use App\Services\Posts\PostStoreService;
use App\Services\Posts\PostUpdateService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->datesFiltering()
            ->customOrdering()
            ->paginate();

        return response()->success($posts->toArray());
    }

    public function store(PostStoreService $service): JsonResponse
    {
        $result = $service->store();
        return response()->json($result->toArray(), $result->code);
    }

    public function update(Post $post, PostUpdateService $service): JsonResponse
    {
        $result = $service->forPost($post)->update();
        return response()->json($result->toArray(), $result->code);
    }

    public function destroy(Post $post, PostDeleteService $service): JsonResponse
    {
        $result = $service->forPosts(new Collection([$post]))->delete();
        return response()->json($result->toArray(), $result->code);
    }

    public function bulkDestroy(PostBulkDeleteService $service): JsonResponse
    {
        $result = $service->delete();
        return response()->json($result->toArray(), $result->code);
    }

    public function statistics(Request $request, PostStatisticsService $service): JsonResponse
    {
        $data = $service->getStatistics(
            $request->input('start_date'),
            $request->input('end_date'),
            $request->input('interval', 'days'),
        );

        return response()->success($data);
    }
}
```

---

## 6. Routes

```php
use App\Http\Controllers\PostController;

Route::get('/posts', [PostController::class, 'index']);
Route::post('/posts', [PostController::class, 'store']);
Route::put('/posts/{post}', [PostController::class, 'update']);
Route::delete('/posts/{post}', [PostController::class, 'destroy']);
Route::delete('/posts', [PostController::class, 'bulkDestroy']);
Route::get('/posts/statistics', [PostController::class, 'statistics']);
```

---

## 7. Sample Requests & Responses

### Create a post with a cover image and inline comments

```bash
curl -X POST /api/posts \
  -F "title=My First Post" \
  -F "body=Hello, world!" \
  -F "cover_image=@/path/to/cover.jpg" \
  -F "comments[0][body]=First comment" \
  -F "tags[]=1" -F "tags[]=2"
```

**Response — `201 Created`:**

```json
{
  "status": "success",
  "messages": ["The record was created successfully."],
  "data": {
    "id": 1,
    "title": "My First Post",
    "body": "Hello, world!",
    "cover_image": "https://your-app.test/storage/posts/1/a1b2c3d4.jpg",
    "status": 1,
    "created_at": "2026-06-19T08:00:00.000000Z",
    "updated_at": "2026-06-19T08:00:00.000000Z"
  },
  "code": 201
}
```

### Update a post, removing the cover image and editing a comment

```bash
curl -X PUT /api/posts/1 \
  -H "Content-Type: application/json" \
  -d '{
    "title": "My First Post (edited)",
    "cover_image": null,
    "comments": [
      { "id": 1, "body": "First comment, edited" }
    ]
  }'
```

**Response — `200 OK`:**

```json
{
  "status": "success",
  "messages": ["The record was updated successfully."],
  "data": {
    "id": 1,
    "title": "My First Post (edited)",
    "cover_image": null,
    "status": 1
  },
  "code": 200
}
```

### Bulk delete

```bash
curl -X DELETE /api/posts \
  -H "Content-Type: application/json" \
  -d '{ "ids": [1, 2, 3] }'
```

**Response — all succeed — `200 OK`:**

```json
{
  "status": "success",
  "messages": ["The record was deleted successfully."],
  "data": [],
  "code": 200
}
```

**Response — partial failure — `207`:**

```json
{
  "status": "partial_success",
  "messages": ["Some records were deleted, but others could not be."],
  "data": [],
  "code": 207,
  "failed_ids": [3]
}
```

### Statistics

```bash
curl "/api/posts/statistics?start_date=2026-06-01&end_date=2026-06-19&interval=days"
```

**Response — `200 OK`:**

```json
{
  "status": "success",
  "messages": ["The operation completed successfully."],
  "data": {
    "2026-06-01": 2,
    "2026-06-02": 0,
    "2026-06-03": 5,
    "...": "...",
    "2026-06-19": 1
  }
}
```

---

## 8. Listening for Domain Events

```php
namespace App\Listeners;

use Nexus\CrudEngine\Events\RecordCreated;
use Illuminate\Support\Facades\Cache;

class InvalidatePostsCache
{
    public function handle(RecordCreated $event): void
    {
        if ($event->model instanceof \App\Models\Post) {
            Cache::forget('posts.index');
        }
    }
}
```

```php
// EventServiceProvider.php
protected $listen = [
    \Nexus\CrudEngine\Events\RecordCreated::class => [
        \App\Listeners\InvalidatePostsCache::class,
    ],
];
```

---

## 9. Testing the Example

```php
namespace Tests\Feature;

use App\Models\Post;
use App\Services\Posts\PostStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class PostStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_post(): void
    {
        $this->app->instance('request', Request::create('/posts', 'POST', [
            'title' => 'Test Post',
        ]));

        $result = $this->app->make(PostStoreService::class)->store();

        $this->assertTrue($result->isSuccessful());
        $this->assertDatabaseHas('posts', ['title' => 'Test Post']);
    }
}
```

See [Testing.md](Testing.md) for the package's own internal test suite, which exercises this exact pattern against fixture models.
