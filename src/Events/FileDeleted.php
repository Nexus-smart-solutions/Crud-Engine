<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\DTOs\FileOperation;

/**
 * Dispatched after a file is successfully deleted (and the model
 * attribute nulled and saved — see the Bug 4.2 fix in
 * {@see \Nexus\CrudEngine\Services\Files\FileLifecycleService::delete()}).
 */
final class FileDeleted
{
    public function __construct(
        public readonly Model $model,
        public readonly FileOperation $operation,
    ) {
    }
}
