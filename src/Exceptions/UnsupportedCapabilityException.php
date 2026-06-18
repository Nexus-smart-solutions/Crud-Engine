<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Exceptions;

/**
 * Optional strict-mode exception: thrown when incoming data references a
 * relation or file key the target model does not declare support for, but
 * only when `crud-engine.strict_capabilities` is enabled in config.
 *
 * By default this check is disabled and unsupported keys are silently
 * ignored, preserving the original codebase's behavior.
 */
class UnsupportedCapabilityException extends CrudEngineException
{
    public static function forRelation(string $relationName, string $modelClass): self
    {
        return new self(
            "Model [{$modelClass}] does not declare support for relation [{$relationName}]. ".
            'Enable strict_capabilities=false in config/crud-engine.php to ignore this instead of throwing.'
        );
    }

    public static function forFileAttribute(string $attribute, string $modelClass): self
    {
        return new self(
            "Model [{$modelClass}] does not declare [{$attribute}] in requestKeysForFile(). ".
            'Enable strict_capabilities=false in config/crud-engine.php to ignore this instead of throwing.'
        );
    }
}
