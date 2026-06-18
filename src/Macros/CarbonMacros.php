<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Macros;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

/**
 * Replaces `Macros/CarbonMacro.php`. Behavior is unchanged — this macro
 * had no global-state dependency or bugs, so it was ported as-is.
 */
final class CarbonMacros
{
    public static function register(): void
    {
        Carbon::macro('parseOrNow', function ($date = '') {
            try {
                return $date ? Carbon::parse($date) : Carbon::now();
            } catch (InvalidFormatException) {
                return Carbon::now();
            }
        });
    }
}
