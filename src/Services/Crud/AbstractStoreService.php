<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Crud;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\Contracts\Repositories\RepositoryInterface;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\Contracts\Services\CreatesRecords;
use Nexus\CrudEngine\Contracts\Validation\RequestValidatorInterface;
use Nexus\CrudEngine\DTOs\CrudOperationResult;
use Nexus\CrudEngine\DTOs\StoreContext;
use Nexus\CrudEngine\Events\RecordCreated;
use Nexus\CrudEngine\Repositories\RepositoryFactory;

/**
 * Replaces `App\Core\Classes\StoringData\AbstractClassHandleStoreData`.
 *
 * Subclasses still only define {@see model()}, {@see requestFile()}, and
 * the message hooks — the same shape application code already uses
 * today — but every collaborator (validation, persistence, file
 * handling, relation syncing, response formatting) is now constructor-
 * injected instead of called statically or pulled from global helpers.
 *
 * Two behavioral fixes from the Phase 1 audit, both applied
 * unconditionally per your confirmation:
 *  - Bug 4.1 (validation result discarded) is fixed inside
 *    {@see RequestValidatorInterface}, which this class depends on.
 *  - Bug 4.6 (file writes inside a DB transaction with no compensation):
 *    file handling now happens AFTER the transaction commits, not inside
 *    it, so a rolled-back transaction can never leave an orphaned file
 *    on disk.
 *  - The inline `Log::info/error` calls from the original are replaced
 *    by a {@see RecordCreated} domain event, dispatched via the injected
 *    event dispatcher (not the `event()` helper). The package's own
 *    {@see \Nexus\CrudEngine\Listeners\LogCrudOperationListener} provides
 *    equivalent logging by default; consuming applications can add their
 *    own listeners instead.
 */
abstract class AbstractStoreService implements CreatesRecords
{
    public function __construct(
        private readonly RequestValidatorInterface $validator,
        private readonly RepositoryFactory $repositoryFactory,
        private readonly FileLifecycleServiceInterface $files,
        private readonly RelationSyncManagerInterface $relations,
        private readonly CapabilityRegistryInterface $capabilities,
        private readonly ResponseFormatterInterface $responses,
        private readonly Dispatcher $events,
        private readonly ConnectionResolverInterface $connectionResolver,
    ) {
    }

    /**
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * @return class-string A class with a public rules(): array method.
     */
    abstract public function requestFile(): string;

    public function successMessage(): string
    {
        return 'crud-engine::responses.success.created';
    }

    public function errorMessage(): string
    {
        return 'crud-engine::responses.error.create_failed';
    }

    /**
     * Hook for subclasses that need to adjust validated data before
     * persistence (e.g. attach an authenticated user id).
     */
    protected function beforePersist(array $data): array
    {
        return $data;
    }

    public function store(): CrudOperationResult
    {
        $data = $this->validator->validate($this->requestFile());
        $data = $this->beforePersist($data);

        $repository = $this->repositoryFactory->make($this->model());

        $connection = $this->connectionResolver->connection();

        /** @var Model $model */
        $model = $connection->transaction(function () use ($repository, $data) {
            return $repository->create($this->withoutFileFields($data, $this->model()));
        });

        // File writes happen after the transaction commits (fixes Bug 4.6
        // — the original wrote files inside the transaction with no
        // compensating delete if the transaction later rolled back).
        $this->handleFiles($model, $data);
        $this->relations->syncAll($model, $data);

        $this->events->dispatch(new RecordCreated($model, new StoreContext($this->model(), $data)));

        return CrudOperationResult::success(
            data: $model->toArray(),
            messages: [$this->successMessage()],
            code: 201,
        );
    }

    private function handleFiles(Model $model, array $data): void
    {
        if (! $this->capabilities->supportsFileUpload($model)) {
            return;
        }

        foreach ($model->requestKeysForFile() as $attribute) {
            if (array_key_exists($attribute, $data)) {
                $this->files->applyIncomingValue($model, $attribute, $data[$attribute]);
            }
        }
    }

    /**
     * File attributes are handled separately via the file lifecycle
     * service (which needs an UploadedFile instance, not a raw value
     * persisted to the column), so they're excluded from the initial
     * mass-assignment call and applied afterward.
     */
    private function withoutFileFields(array $data, string $modelClass): array
    {
        /** @var Model $probe */
        $probe = new $modelClass();

        if (! $this->capabilities->supportsFileUpload($probe)) {
            return $data;
        }

        foreach ($probe->requestKeysForFile() as $attribute) {
            unset($data[$attribute]);
        }

        return $data;
    }
}
