<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Capabilities;

/**
 * Marks an Eloquent model as owning one or more file-backed attributes.
 *
 * Implementing this interface is how a model opts in to automatic file
 * storage, deletion, and URL rewriting via {@see \Nexus\CrudEngine\Contracts\Files\FileLifecycleServiceInterface}.
 */
interface FileUpload
{
    /**
     * The storage path (relative to the configured disk root) under which
     * this model's files are stored, e.g. "users/{id}" or "products/{id}".
     *
     * The package never assumes a fixed structure — this method is the
     * single source of truth for "where do this model's files live."
     */
    public function documentFullPathStore(): string;

    /**
     * The list of attribute names on this model that represent file fields
     * (e.g. ['avatar', 'cover_image']). Each of these is eligible for
     * automatic store/delete/URL-rewrite handling.
     *
     * @return string[]
     */
    public function requestKeysForFile(): array;
}
