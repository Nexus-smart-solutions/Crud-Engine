<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;

/**
 * Child model for "has many" sync tests. Implements FileUpload so a
 * single test can cover nested-relation file handling too.
 */
class Comment extends Model implements FileUpload
{
    protected $guarded = [];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function documentFullPathStore(): string
    {
        return 'comments/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['attachment'];
    }
}
