<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Services;

use Nexus\CrudEngine\Services\Statistics\AbstractStatisticsService;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;

final class ArticleStatisticsService extends AbstractStatisticsService
{
    public function getModelClass(): string
    {
        return Article::class;
    }

    public function getDateColumn(): string
    {
        return 'created_at';
    }
}
