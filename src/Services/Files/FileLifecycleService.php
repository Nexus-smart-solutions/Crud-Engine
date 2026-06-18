<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Files;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;
use Nexus\CrudEngine\Contracts\Files\FileNamingStrategyInterface;
use Nexus\CrudEngine\Contracts\Files\FilePathResolverInterface;
use Nexus\CrudEngine\DTOs\Enums\FileOperationType;
use Nexus\CrudEngine\DTOs\FileOperation;
use Nexus\CrudEngine\Events\FileDeleted;
use Nexus\CrudEngine\Events\FileStored;
use Nexus\CrudEngine\Exceptions\FileOperationException;
use Nexus\CrudEngine\Helpers\PathHelper;

/**
 * Replaces the original static `StoragePictures` class.
 *
 * Two behavioral fixes versus the original, both from the Phase 1 audit:
 *
 *  - Bug 4.2: {@see delete()} now nulls out and saves the model attribute
 *    after removing the physical file, instead of leaving the database
 *    pointing at a file that no longer exists.
 *  - The disk to use is resolved per call (constructor-injected
 *    `FilesystemFactory`, disk name read from config), rather than a
 *    single hardcoded `config('filesystems.default')` call baked into a
 *    static method — this makes multi-disk setups and unit testing with
 *    a fake disk straightforward.
 */
final class FileLifecycleService implements FileLifecycleServiceInterface
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly CapabilityRegistryInterface $capabilities,
        private readonly FilePathResolverInterface $pathResolver,
        private readonly FileNamingStrategyInterface $hashedNamingStrategy,
        private readonly FileNamingStrategyInterface $originalNamingStrategy,
        private readonly Dispatcher $events,
        private readonly string $disk,
    ) {
    }

    public function store(Model $model, string $attribute, UploadedFile $file): FileOperation
    {
        try {
            $directory = $this->pathResolver->resolve($model);
            $namingStrategy = $this->capabilities->usesOriginalFilename($model)
                ? $this->originalNamingStrategy
                : $this->hashedNamingStrategy;

            $fileName = $namingStrategy->generateName($file, $model);

            $this->disk()->putFileAs($directory, $file, $fileName);

            $model->{$attribute} = $fileName;
            $model->save();

            $operation = new FileOperation(FileOperationType::Stored, $attribute, $fileName, $this->url($model, $fileName));
            $this->events->dispatch(new FileStored($model, $operation));

            return $operation;
        } catch (\Throwable $exception) {
            throw FileOperationException::storeFailed($attribute, $exception->getMessage());
        }
    }

    public function delete(Model $model, string $attribute): FileOperation
    {
        try {
            $currentFileName = $model->{$attribute};

            if ($currentFileName) {
                $directory = $this->pathResolver->resolve($model);
                $this->disk()->delete(PathHelper::joinPath($directory, $currentFileName));
            }

            // Fix for Bug 4.2: null out and persist the attribute so the
            // database never references a file that no longer exists.
            $model->{$attribute} = null;
            $model->save();

            $operation = new FileOperation(FileOperationType::Deleted, $attribute, $currentFileName);
            $this->events->dispatch(new FileDeleted($model, $operation));

            return $operation;
        } catch (\Throwable $exception) {
            throw FileOperationException::deleteFailed($attribute, $exception->getMessage());
        }
    }

    public function url(Model $model, string $fileName): string
    {
        $directory = $this->pathResolver->resolve($model);

        return $this->disk()->url(PathHelper::joinPath($directory, $fileName));
    }

    public function applyIncomingValue(Model $model, string $attribute, mixed $incomingValue): ?FileOperation
    {
        if ($incomingValue instanceof UploadedFile) {
            return $this->store($model, $attribute, $incomingValue);
        }

        if ($incomingValue === null) {
            return $this->delete($model, $attribute);
        }

        return null;
    }

    private function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return $this->filesystem->disk($this->disk);
    }
}
