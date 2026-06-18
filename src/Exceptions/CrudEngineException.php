<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Exceptions;

/**
 * Base exception for every exception thrown by this package. Consuming
 * applications can catch this single type to handle any package failure
 * generically, or catch the more specific subclasses for targeted
 * handling.
 */
class CrudEngineException extends \RuntimeException
{
}
