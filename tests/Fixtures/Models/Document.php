<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Capabilities\OriginalName;

/**
 * Implements both FileUpload and OriginalName, so the file lifecycle
 * service selects {@see \Nexus\CrudEngine\Strategies\Files\OriginalFilenameStrategy}
 * for it — the strategy under test for the Security Finding S4
 * (path-traversal) regression.
 */
class Document extends Model implements FileUpload, OriginalName
{
    protected $guarded = [];

    public function documentFullPathStore(): string
    {
        return 'documents/'.$this->getKey();
    }

    public function requestKeysForFile(): array
    {
        return ['original_file'];
    }
}
