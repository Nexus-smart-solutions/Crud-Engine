<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Nexus\CrudEngine\Contracts\Files\FileNamingStrategyInterface;

/**
 * Default file naming strategy: delegates to Laravel's own collision-safe
 * hashed filename generation. Used for every model unless it implements
 * {@see \Nexus\CrudEngine\Contracts\Capabilities\OriginalName}.
 */
final class HashedFilenameStrategy implements FileNamingStrategyInterface
{
    public function generateName(UploadedFile $file, Model $model): string
    {
        return $file->hashName();
    }
}
