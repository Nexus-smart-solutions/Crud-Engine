<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Decides what filename an uploaded file is stored under.
 *
 * Two implementations ship with the package: a hashed-name strategy
 * (default, safest) and an original-name strategy (opt-in via the
 * {@see \Nexus\CrudEngine\Contracts\Capabilities\OriginalName} marker
 * interface). Consuming applications may bind their own implementation
 * to change naming behavior package-wide without touching the file
 * lifecycle service itself.
 */
interface FileNamingStrategyInterface
{
    public function generateName(UploadedFile $file, Model $model): string;
}
