<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts;

/**
 * Single source of truth for "what can this model do?"
 *
 * Replaces the duplicated `instanceof` checks that were independently
 * implemented in six different places in the original codebase (and were
 * directly responsible for a copy-paste bug where HasOne recursion called
 * the HasMany capability check by mistake). Every Crud service, strategy,
 * and trait in this package asks this registry instead of performing its
 * own `instanceof` check.
 *
 * Swappable: bind your own implementation (e.g. attribute-based reflection
 * instead of interface checks) in your application's service provider.
 */
interface CapabilityRegistryInterface
{
    public function supportsFileUpload(object $model): bool;

    public function supportsHasMany(object $model): bool;

    public function supportsHasOne(object $model): bool;

    public function supportsManyToMany(object $model): bool;

    public function usesOriginalFilename(object $model): bool;
}
