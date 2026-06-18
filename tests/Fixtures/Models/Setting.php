<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grandchild model in the Article -> Profile -> Settings chain used to
 * regression-test Bug 4.3. Has no further nested capabilities — it's
 * the leaf of the recursion.
 */
class Setting extends Model
{
    protected $guarded = [];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
