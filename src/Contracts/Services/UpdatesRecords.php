<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Services;

use Nexus\CrudEngine\DTOs\CrudOperationResult;

/**
 * Contract for an "update" Crud operation.
 */
interface UpdatesRecords
{
    public function update(): CrudOperationResult;
}
