<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Facades;

use Illuminate\Support\Facades\Facade;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;

/**
 * Optional convenience facade for ad-hoc introspection.
 *
 * @method static bool supportsFileUpload(object $model)
 * @method static bool supportsHasMany(object $model)
 * @method static bool supportsHasOne(object $model)
 * @method static bool supportsManyToMany(object $model)
 * @method static bool usesOriginalFilename(object $model)
 *
 * @see CapabilityRegistryInterface
 *
 * This facade is sugar, never required — the package's primary API is
 * constructor injection against the contracts in `Nexus\CrudEngine\Contracts`.
 */
final class CrudEngine extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CapabilityRegistryInterface::class;
    }
}
