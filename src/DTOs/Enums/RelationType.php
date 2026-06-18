<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs\Enums;

enum RelationType: string
{
    case HasMany = 'has_many';
    case HasOne = 'has_one';
    case ManyToMany = 'many_to_many';
}
