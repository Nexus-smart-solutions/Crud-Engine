# Listeners

---

## `LogCrudOperationListener`

**Namespace:** `Nexus\CrudEngine\Listeners\LogCrudOperationListener`

**Registration:** Automatically registered during `CrudEngineServiceProvider::boot()` when `crud-engine.log_operations` is `true` (the default). Uses the `subscribe()` method pattern rather than `$listen` array — it subscribes itself to the event dispatcher directly.

**Purpose:** Replaces the inline `Log::info()`/`Log::error()` calls that existed only inside `AbstractClassHandleStoreData` in the original codebase (and were entirely absent from Update and Delete, an inconsistency this listener corrects by providing consistent logging across all seven events).

**Constructor:**

```php
public function __construct(private readonly LoggerInterface $logger)
// LoggerInterface = Psr\Log\LoggerInterface — resolved from the container
```

**`subscribe()` registers these handlers:**

| Event | Handler method | Log level | Context keys |
|---|---|---|---|
| `RecordCreated` | `onRecordCreated` | `info` | `model`, `id` |
| `RecordUpdated` | `onRecordUpdated` | `info` | `model`, `id` |
| `RecordDeleted` | `onRecordDeleted` | `info` | `model`, `id` |
| `RecordDeletionFailed` | `onRecordDeletionFailed` | `error` | `model`, `id`, `exception` |
| `FileStored` | `onFileStored` | `info` | `model`, `attribute`, `file` |
| `FileDeleted` | `onFileDeleted` | `info` | `model`, `attribute` |
| `RelationSynced` | `onRelationSynced` | `info` | `model`, `relation`, `type` |

**Log message format:**

```
crud-engine: record created  {"model":"App\\Models\\Post","id":42}
crud-engine: file stored     {"model":"App\\Models\\Post","attribute":"cover_image","file":"a1b2c3.jpg"}
crud-engine: record deletion failed  {"model":"App\\Models\\Post","id":5,"exception":"SQLSTATE[23000]..."}
```

---

## Disabling the Default Listener

Set `crud-engine.log_operations = false` in `config/crud-engine.php` (or `CRUD_ENGINE_LOG_OPERATIONS=false` in `.env` if you add that key to your published config):

```php
'log_operations' => false,
```

The events are still dispatched — only this listener is not registered. Your own listeners continue to fire normally.

---

## Adding Your Own Listeners

Your listeners are completely independent — register them in `EventServiceProvider` as you normally would. They fire alongside the default listener (or alone if the default is disabled).

**Example — cache invalidation listener:**

```php
namespace App\Listeners;

use Nexus\CrudEngine\Events\RecordCreated;
use Nexus\CrudEngine\Events\RecordUpdated;
use Nexus\CrudEngine\Events\RecordDeleted;

class InvalidatePostCache
{
    public function handle(RecordCreated|RecordUpdated|RecordDeleted $event): void
    {
        $id = $event->model->getKey();
        cache()->forget("post:{$id}");
        cache()->forget('posts.index');
    }
}
```

**Example — Slack alert on deletion failure:**

```php
namespace App\Listeners;

use Nexus\CrudEngine\Events\RecordDeletionFailed;

class AlertOnDeletionFailure
{
    public function handle(RecordDeletionFailed $event): void
    {
        $model = $event->model::class;
        $id    = $event->model->getKey();
        $msg   = $event->exception->getMessage();

        app(\App\Services\SlackAlerter::class)->send(
            "Deletion failed: {$model} #{$id} — {$msg}"
        );
    }
}
```

**Registering in EventServiceProvider:**

```php
protected $listen = [
    \Nexus\CrudEngine\Events\RecordCreated::class  => [\App\Listeners\InvalidatePostCache::class],
    \Nexus\CrudEngine\Events\RecordUpdated::class  => [\App\Listeners\InvalidatePostCache::class],
    \Nexus\CrudEngine\Events\RecordDeleted::class  => [\App\Listeners\InvalidatePostCache::class],
    \Nexus\CrudEngine\Events\RecordDeletionFailed::class => [\App\Listeners\AlertOnDeletionFailure::class],
];
```

---

## Common Mistakes

**Expecting the listener to run synchronously in queue workers.**

The listener uses `$this->logger->info/error` directly — it is synchronous and does not queue itself. If log I/O is a concern under high throughput, set `log_operations = false` and replace with a queued listener of your own.

**Assuming the `model` property still exists in the database when `RecordDeleted` fires.**

The model instance is available (its attributes and key are accessible from memory), but the corresponding database row has already been deleted. Do not call `$event->model->refresh()` inside a `RecordDeleted` listener.
