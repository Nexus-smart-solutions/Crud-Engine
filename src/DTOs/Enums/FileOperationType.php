<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs\Enums;

enum FileOperationType: string
{
    case Stored = 'stored';
    case Deleted = 'deleted';
    case Skipped = 'skipped';
}
