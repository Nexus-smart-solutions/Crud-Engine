<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Services;

use Nexus\CrudEngine\DTOs\CrudOperationResult;

/**
 * Contract for a "delete" Crud operation (single or bulk).
 */
interface DeletesRecords
{
    public function delete(): CrudOperationResult;
}
