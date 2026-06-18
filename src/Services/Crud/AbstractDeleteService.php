<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Crud;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Services\DeletesRecords;
use Nexus\CrudEngine\DTOs\CrudOperationResult;
use Nexus\CrudEngine\Events\RecordDeleted;
use Nexus\CrudEngine\Events\RecordDeletionFailed;

/**
 * Replaces `App\Core\Classes\DeletingData\AbstractClassHandleDelete`.
 *
 * Fixes Bug 4.7 from the Phase 1 audit: the original
 * `deleteModelWithFiles()` caught every `\Throwable` and returned `false`
 * with zero logging, making failures impossible to diagnose in
 * production. Here, every failure dispatches a {@see RecordDeletionFailed}
 * event (carrying the exception) before being aggregated into the
 * result — the package's default listener logs it, and consuming
 * applications can attach their own listener for alerting.
 */
abstract class AbstractDeleteService implements DeletesRecords
{
    public function __construct(
        private readonly FileLifecycleServiceInterface $files,
        private readonly CapabilityRegistryInterface $capabilities,
        private readonly Dispatcher $events,
    ) {
    }

    /**
     * @return class-string<Model>
     */
    abstract public function model(): string;

    /**
     * The model(s) to delete, already resolved (e.g. by id lookup).
     */
    abstract public function resolveTargets(): Collection;

    public function successMessage(): string
    {
        return 'crud-engine::responses.success.deleted';
    }

    public function partialSuccessMessage(): string
    {
        return 'crud-engine::responses.error.partial_delete';
    }

    public function errorMessage(): string
    {
        return 'crud-engine::responses.error.delete_failed';
    }

    public function delete(): CrudOperationResult
    {
        $targets = $this->resolveTargets();

        if ($targets->isEmpty()) {
            return CrudOperationResult::error([$this->errorMessage()], 404);
        }

        $failedIds = [];

        foreach ($targets as $target) {
            try {
                $this->deleteOneWithFiles($target);
                $this->events->dispatch(new RecordDeleted($target));
            } catch (\Throwable $exception) {
                $failedIds[] = $target->getKey();
                $this->events->dispatch(new RecordDeletionFailed($target, $exception));
            }
        }

        if ($failedIds === []) {
            return CrudOperationResult::success(messages: [$this->successMessage()]);
        }

        if (count($failedIds) === $targets->count()) {
            return CrudOperationResult::error([$this->errorMessage()], 500, ['failed_ids' => $failedIds]);
        }

        return CrudOperationResult::partialSuccess([$this->partialSuccessMessage()], $failedIds);
    }

    private function deleteOneWithFiles(Model $model): void
    {
        if ($this->capabilities->supportsFileUpload($model)) {
            foreach ($model->requestKeysForFile() as $attribute) {
                if ($model->{$attribute}) {
                    $this->files->delete($model, $attribute);
                }
            }
        }

        $model->delete();
    }
}
