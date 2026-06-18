<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Nexus\CrudEngine\Contracts\Files\FileNamingStrategyInterface;

/**
 * Used when a model implements {@see \Nexus\CrudEngine\Contracts\Capabilities\OriginalName}.
 *
 * Fixes Security Finding S4 from the Phase 1 audit: the original
 * implementation used the client-supplied original filename almost
 * verbatim (`str_replace(' ', '_', $file->getClientOriginalName())`),
 * which could contain path-traversal sequences, null bytes, or other
 * unsafe characters that flowed unsanitized into both storage and
 * deletion paths.
 *
 * This strategy:
 *  - strips any directory component via basename() (defeats `../../etc`-
 *    style traversal regardless of OS path separator),
 *  - removes control/null-byte characters,
 *  - allow-lists the remaining filename to [A-Za-z0-9 ._-] only,
 *  - replaces spaces with underscores (preserving the original behavior
 *    for the common case),
 *  - falls back to a hashed name if sanitization leaves nothing usable.
 *
 * Note: because the original filename is preserved by design (that is
 * the entire point of this strategy), two uploads sharing the same
 * original filename for the same model can still overwrite one another.
 * That collision risk is inherent to "keep the original name" semantics,
 * not introduced by this fix; consuming applications that need collision
 * safety should not implement OriginalName.
 */
final class OriginalFilenameStrategy implements FileNamingStrategyInterface
{
    public function generateName(UploadedFile $file, Model $model): string
    {
        $original = basename($file->getClientOriginalName());

        // Strip control characters and null bytes outright.
        $original = preg_replace('/[\x00-\x1F\x7F]/', '', $original) ?? '';

        // Allow-list: letters, digits, spaces, dots, underscores, hyphens.
        $sanitized = preg_replace('/[^A-Za-z0-9 ._-]/', '', $original) ?? '';

        // Collapse repeated dots (defensive — basename() already removes
        // the path separators that would make ".." dangerous on its own).
        $sanitized = preg_replace('/\.{2,}/', '.', $sanitized) ?? '';

        $sanitized = trim(str_replace(' ', '_', $sanitized), '._-');

        if ($sanitized === '') {
            return $file->hashName();
        }

        return $sanitized;
    }
}
