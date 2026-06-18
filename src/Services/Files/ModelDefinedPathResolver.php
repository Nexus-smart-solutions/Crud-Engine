<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Files;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Files\FilePathResolverInterface;
use Nexus\CrudEngine\Exceptions\FileOperationException;

/**
 * Default path resolver: delegates to the model's own
 * {@see FileUpload::documentFullPathStore()} method.
 *
 * Per your clarification #4 ("do not assume a fixed structure... the
 * package should expose contracts and allow consumers to define their
 * own paths"), this is intentionally the thinnest possible
 * implementation — the actual path logic lives entirely in the
 * consuming application's model. Applications that want centralized
 * path logic instead of per-model methods can bind a different
 * implementation of {@see FilePathResolverInterface} in their own
 * service provider.
 */
final class ModelDefinedPathResolver implements FilePathResolverInterface
{
    public function resolve(Model $model): string
    {
        if (! $model instanceof FileUpload) {
            throw new FileOperationException(
                sprintf('Model [%s] must implement %s to resolve a file storage path.', $model::class, FileUpload::class)
            );
        }

        return $model->documentFullPathStore();
    }
}
