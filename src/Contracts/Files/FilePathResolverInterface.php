<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Files;

use Illuminate\Database\Eloquent\Model;

/**
 * Resolves the storage directory for a given model's files.
 *
 * The package never assumes a fixed structure ("users/{id}",
 * "products/{id}", "orders/{id}" are all just examples). The default
 * implementation delegates to the model's own
 * {@see \Nexus\CrudEngine\Contracts\Capabilities\FileUpload::documentFullPathStore()}
 * method, but consuming applications are free to bind a different
 * implementation in their own service provider to centralize path logic
 * instead of defining it per model (e.g. a tenant-aware resolver).
 */
interface FilePathResolverInterface
{
    public function resolve(Model $model): string;
}
