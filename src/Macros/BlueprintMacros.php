<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Macros;

use Illuminate\Database\Schema\Blueprint;

/**
 * Replaces `Macros/BlueprintMacro.php`. Registered by
 * {@see \Nexus\CrudEngine\Providers\CrudEngineServiceProvider::boot()},
 * not by manual `require` in an application service provider.
 *
 * Behavior is unchanged from the original — these are simple column
 * shortcuts with no global-state dependency, so they were ported as-is.
 */
final class BlueprintMacros
{
    public static function register(): void
    {
        Blueprint::macro('status', function (int $default = 1) {
            /** @var Blueprint $this */
            return $this->tinyInteger('status')->default($default);
        });

        Blueprint::macro('standardTime', function () {
            /** @var Blueprint $this */
            $this->timestamp('created_at')->useCurrent();
            $this->timestamp('updated_at')->nullable();
            $this->softDeletes();
        });
    }
}
