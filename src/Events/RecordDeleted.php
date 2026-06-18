<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * Dispatched after a single record (and its files) is successfully
 * deleted, including each successful deletion within a bulk operation.
 */
final class RecordDeleted
{
    public function __construct(public readonly Model $model)
    {
    }
}
