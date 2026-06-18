<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Macros;

use Illuminate\Support\Str;

/**
 * Replaces `Macros/StrMacro.php`. Behavior is unchanged — these helpers
 * had no global-state dependency or bugs, so they were ported as-is.
 */
final class StrMacros
{
    public static function register(): void
    {
        Str::macro('snakeToTitle', function (string $value) {
            return ucwords(str_replace('_', ' ', $value));
        });

        Str::macro('humanText', function (string $value) {
            $text = preg_replace('/[^a-zA-Z0-9]+/', ' ', $value);

            return Str::title($text ?? '');
        });
    }
}
