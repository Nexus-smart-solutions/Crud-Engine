<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Capabilities\HasManyRelations;
use Nexus\CrudEngine\Contracts\Capabilities\HasOneRelations;
use Nexus\CrudEngine\Contracts\Capabilities\ManyToManyRelations;
use Nexus\CrudEngine\Traits\HasFileUrlsTrait;

class Article extends Model implements FileUpload, HasManyRelations, HasOneRelations, ManyToManyRelations
{
    use HasFileUrlsTrait;

    protected $guarded = [];

    public function documentFullPathStore(): string
    {
        return 'articles/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['cover_image'];
    }

    public function getHasManyRelations(): array
    {
        return ['comments'];
    }

    public function getHasOneRelations(): array
    {
        return ['profile'];
    }

    public function getManyToManyRelations(): array
    {
        return ['tags'];
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
