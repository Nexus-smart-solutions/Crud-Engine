<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nexus\CrudEngine\Contracts\Capabilities\HasOneRelations;

/**
 * Deliberately implements ONLY HasOneRelations, not HasManyRelations.
 *
 * This is the exact shape needed to regression-test Bug 4.3: the
 * original `HandleRelationHasOne` recursed into a nested relation by
 * calling `$existingRecord->getHasManyRelations()` — a method this
 * model does not define. Under the original buggy code, syncing
 * Article -> Profile -> Settings would throw a fatal "call to undefined
 * method" error. Under the fixed implementation, recursion goes through
 * the CapabilityRegistry and correctly calls getHasOneRelations()
 * instead.
 */
class Profile extends Model implements HasOneRelations
{
    protected $guarded = [];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function settings(): HasOne
    {
        return $this->hasOne(Setting::class);
    }

    public function getHasOneRelations(): array
    {
        return ['settings'];
    }
}
