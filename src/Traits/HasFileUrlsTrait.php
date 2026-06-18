<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Traits;

use Nexus\CrudEngine\Contracts\Capabilities\FileUpload;
use Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface;

/**
 * Successor to the standalone `HandleToArrayTrait` you uploaded.
 *
 * Overrides `toArray()` so file attributes are rewritten into full URLs
 * in API responses, exactly as the original trait did.
 *
 * Resolving {@see FileLifecycleServiceInterface} via the container inside
 * {@see toArray()} is a deliberate, narrow exception to this package's
 * "no app()/container pulls in business logic" rule, documented here
 * rather than hidden: Eloquent models are instantiated by Eloquent
 * itself (via `new`, hydration, factories), never by the service
 * container, so constructor injection is not possible for a trait
 * applied to a Model. Every other class in this package uses
 * constructor injection exclusively.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait HasFileUrlsTrait
{
    public function toArray(): array
    {
        $data = parent::toArray();

        if (! $this instanceof FileUpload) {
            return $data;
        }

        /** @var FileLifecycleServiceInterface $files */
        $files = app(FileLifecycleServiceInterface::class);

        foreach ($this->requestKeysForFile() as $attribute) {
            if (isset($data[$attribute]) && $data[$attribute] !== null) {
                $data[$attribute] = $files->url($this, $data[$attribute]);
            }
        }

        return $data;
    }
}
