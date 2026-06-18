<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Capabilities;

/**
 * Marker interface: when a model implements this, the file naming strategy
 * resolver selects {@see \Nexus\CrudEngine\Strategies\Files\OriginalFilenameStrategy}
 * instead of the default hashed-filename strategy.
 *
 * No methods — presence of the interface is the signal.
 */
interface OriginalName
{
}
