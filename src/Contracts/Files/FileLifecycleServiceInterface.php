<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Files;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Nexus\CrudEngine\DTOs\FileOperation;

/**
 * Replaces the original static `StoragePictures` class.
 *
 * Owns the full lifecycle of a model's file-backed attributes: storing a
 * newly uploaded file, deleting an existing one, building a public URL,
 * and — critically — keeping the database attribute and the physical file
 * in sync (the original code deleted files without nulling the DB column;
 * this contract's `delete()` is specified to do both).
 */
interface FileLifecycleServiceInterface
{
    /**
     * Store an uploaded file for the given model attribute and persist the
     * resulting filename onto the model (saves the model).
     */
    public function store(Model $model, string $attribute, UploadedFile $file): FileOperation;

    /**
     * Delete the physical file for the given model attribute (if one
     * exists) AND null out + save the model attribute, so the database
     * never references a file that no longer exists.
     */
    public function delete(Model $model, string $attribute): FileOperation;

    /**
     * Build a fully-qualified, disk-aware URL for an existing file.
     */
    public function url(Model $model, string $fileName): string;

    /**
     * Apply a single incoming value for a file attribute: an UploadedFile
     * triggers store(), an explicit null triggers delete(), anything else
     * is a no-op. This is the single entry point Crud services use instead
     * of re-implementing the same branching logic per call site.
     */
    public function applyIncomingValue(Model $model, string $attribute, mixed $incomingValue): ?FileOperation;
}
