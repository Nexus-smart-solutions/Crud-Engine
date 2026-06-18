<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\DTOs\FileOperation;

/**
 * Dispatched after a file is successfully stored for a model attribute.
 */
final class FileStored
{
    public function __construct(
        public readonly Model $model,
        public readonly FileOperation $operation,
    ) {
    }
}
