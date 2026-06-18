<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Crud;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Relations\RelationSyncManagerInterface;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\Contracts\Services\UpdatesRecords;
use Nexus\CrudEngine\Contracts\Validation\RequestValidatorInterface;
use Nexus\CrudEngine\DTOs\CrudOperationResult;
use Nexus\CrudEngine\DTOs\UpdateContext;
use Nexus\CrudEngine\Events\RecordUpdated;
use Nexus\CrudEngine\Repositories\RepositoryFactory;

/**
 * Replaces `App\Core\Classes\UpdatingData\AbstractClassHandleUpdate`.
 *
 * Same structural fixes as {@see AbstractStoreService}: validation
 * returns only validated fields, file writes happen after the
 * transaction commits rather than inside it, and a {@see RecordUpdated}
 * event replaces ad-hoc logging (the original Update class had no
 * logging at all, unlike Store — this restores parity between the two).
 */
abstract class AbstractUpdateService implements UpdatesRecords
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

    abstract public function resolveModel(): Model;

    public function successMessage(): string
    {
        return 'crud-engine::responses.success.updated';
    }

    public function errorMessage(): string
    {
        return 'crud-engine::responses.error.update_failed';
    }

    protected function beforePersist(array $data, Model $model): array
    {
        return $data;
    }

    public function update(): CrudOperationResult
    {
        $model = $this->resolveModel();

        $data = $this->validator->validate($this->requestFile());
        $data = $this->beforePersist($data, $model);

        $repository = $this->repositoryFactory->make($this->model());

        $connection = $this->connectionResolver->connection();

        $connection->transaction(function () use ($repository, $model, $data) {
            $repository->update($model, $this->withoutFileFields($data, $model));
        });

        // File writes happen after the transaction commits, for the same
        // reason as AbstractStoreService (Bug 4.6 fix).
        $this->handleFiles($model, $data);
        $this->relations->syncAll($model, $data);

        $this->events->dispatch(new RecordUpdated($model, new UpdateContext($model, $data)));

        return CrudOperationResult::success(
            data: $model->refresh()->toArray(),
            messages: [$this->successMessage()],
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

    private function withoutFileFields(array $data, Model $model): array
    {
        if (! $this->capabilities->supportsFileUpload($model)) {
            return $data;
        }

        foreach ($model->requestKeysForFile() as $attribute) {
            unset($data[$attribute]);
        }

        return $data;
    }
}
