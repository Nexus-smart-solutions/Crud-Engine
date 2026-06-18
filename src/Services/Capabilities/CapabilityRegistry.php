<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Capabilities;

use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;
use Nexus\CrudEngine\Contracts\Capabilities\HasOneRelations;
use Nexus\CrudEngine\Contracts\Capabilities\ManyToManyRelations;
use Nexus\CrudEngine\Contracts\Capabilities\OriginalName;
use Nexus\CrudEngine\Contracts\CapabilityRegistryInterface;

/**
 * Default capability registry: a thin, stateless wrapper around
 * `instanceof` checks against the package's capability interfaces.
 *
 * This is the single place that decides "does this model support X" —
 * every other class in the package asks this registry instead of
 * performing its own check. Centralizing it here is the direct fix for
 * the original codebase's Bug 4.3 (HasOne recursion accidentally calling
 * the HasMany capability check because the same logic was duplicated,
 * independently, in six different files).
 */
final class CapabilityRegistry implements CapabilityRegistryInterface
{
    public function supportsFileUpload(object $model): bool
    {
        return $model instanceof FileUpload;
    }

    public function supportsHasMany(object $model): bool
    {
        return $model instanceof HasManyRelations;
    }

    public function supportsHasOne(object $model): bool
    {
        return $model instanceof HasOneRelations;
    }

    public function supportsManyToMany(object $model): bool
    {
        return $model instanceof ManyToManyRelations;
    }

    public function usesOriginalFilename(object $model): bool
    {
        return $model instanceof OriginalName;
    }
}
