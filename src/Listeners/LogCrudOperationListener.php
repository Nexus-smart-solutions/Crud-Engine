<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Nexus\CrudEngine\Events\FileDeleted;
use Nexus\CrudEngine\Events\FileStored;
use Nexus\CrudEngine\Events\RecordCreated;
use Nexus\CrudEngine\Events\RecordDeleted;
use Nexus\CrudEngine\Events\RecordDeletionFailed;
use Nexus\CrudEngine\Events\RecordUpdated;
use Nexus\CrudEngine\Events\RelationSynced;

/**
 * Default logging behavior, replacing the inline `Log::info()`/`Log::error()`
 * calls that were hardcoded into `AbstractClassHandleStoreData` in the
 * original codebase (and were entirely absent from Update/Delete,
 * an inconsistency this restores parity for).
 *
 * Registered automatically by {@see \Nexus\CrudEngine\Providers\CrudEngineServiceProvider}
 * unless `crud-engine.log_operations` is set to `false`. Consuming
 * applications can subscribe their own listeners to the same events
 * instead of, or in addition to, this one.
 */
final class LogCrudOperationListener
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(RecordCreated::class, [$this, 'onRecordCreated']);
        $events->listen(RecordUpdated::class, [$this, 'onRecordUpdated']);
        $events->listen(RecordDeleted::class, [$this, 'onRecordDeleted']);
        $events->listen(RecordDeletionFailed::class, [$this, 'onRecordDeletionFailed']);
        $events->listen(FileStored::class, [$this, 'onFileStored']);
        $events->listen(FileDeleted::class, [$this, 'onFileDeleted']);
        $events->listen(RelationSynced::class, [$this, 'onRelationSynced']);
    }

    public function onRecordCreated(RecordCreated $event): void
    {
        $this->logger->info('crud-engine: record created', [
            'model' => $event->model::class,
            'id' => $event->model->getKey(),
        ]);
    }

    public function onRecordUpdated(RecordUpdated $event): void
    {
        $this->logger->info('crud-engine: record updated', [
            'model' => $event->model::class,
            'id' => $event->model->getKey(),
        ]);
    }

    public function onRecordDeleted(RecordDeleted $event): void
    {
        $this->logger->info('crud-engine: record deleted', [
            'model' => $event->model::class,
            'id' => $event->model->getKey(),
        ]);
    }

    public function onRecordDeletionFailed(RecordDeletionFailed $event): void
    {
        $this->logger->error('crud-engine: record deletion failed', [
            'model' => $event->model::class,
            'id' => $event->model->getKey(),
            'exception' => $event->exception->getMessage(),
        ]);
    }

    public function onFileStored(FileStored $event): void
    {
        $this->logger->info('crud-engine: file stored', [
            'model' => $event->model::class,
            'attribute' => $event->operation->attribute,
            'file' => $event->operation->fileName,
        ]);
    }

    public function onFileDeleted(FileDeleted $event): void
    {
        $this->logger->info('crud-engine: file deleted', [
            'model' => $event->model::class,
            'attribute' => $event->operation->attribute,
        ]);
    }

    public function onRelationSynced(RelationSynced $event): void
    {
        $this->logger->info('crud-engine: relation synced', [
            'model' => $event->model::class,
            'relation' => $event->relationName,
            'type' => $event->type->value,
        ]);
    }
}
