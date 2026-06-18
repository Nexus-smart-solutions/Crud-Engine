<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched when deleting a single record (within a single or bulk
 * delete operation) fails.
 *
 * Carries the original exception — the direct fix for Bug 4.7, where the
 * original `deleteModelWithFiles()` caught every `\Throwable` and
 * returned `false` with no logging at all.
 */
final class RecordDeletionFailed
{
    public function __construct(
        public readonly Model $model,
        public readonly \Throwable $exception,
    ) {
    }
}
