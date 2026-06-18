<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Services;

use Nexus\CrudEngine\Services\Crud\AbstractBulkDeleteService;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;

/**
 * No extra wiring needed beyond what {@see AbstractBulkDeleteService}
 * already constructor-injects (including the current Request, which
 * Laravel resolves automatically) — this is the minimal subclass shape
 * your clarification #5 asked for.
 */
final class ArticleBulkDeleteService extends AbstractBulkDeleteService
{
    public function model(): string
    {
        return Article::class;
    }
}
