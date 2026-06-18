<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Exceptions;

/**
 * Thrown when a file store/delete operation fails in a way the caller
 * needs to know about (as opposed to the original codebase's
 * `deleteModelWithFiles()`, which silently swallowed every `\Throwable`).
 */
class FileOperationException extends CrudEngineException
{
    public static function storeFailed(string $attribute, string $reason): self
    {
        return new self("Failed to store file for attribute [{$attribute}]: {$reason}");
    }

    public static function deleteFailed(string $attribute, string $reason): self
    {
        return new self("Failed to delete file for attribute [{$attribute}]: {$reason}");
    }
}
