<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Statistics;

use Nexus\CrudEngine\DTOs\StatisticsQuery;

/**
 * Executes a time-bucketed aggregate query (count or sum, grouped by
 * day/month/year) and returns raw {date_group => value} rows.
 *
 * Two implementations ship with the package:
 *  - EloquentAggregateStrategy (default, zero extra dependencies, portable
 *    across MySQL/Postgres/SQLite)
 *  - SpatieQueryBuilderStrategy (optional — only usable if
 *    spatie/laravel-query-builder is installed; the package never
 *    requires it)
 */
interface StatisticsQueryStrategyInterface
{
    /**
     * @return array<string, float|int> Map of date_group => aggregate value, unordered.
     */
    public function execute(StatisticsQuery $query): array;
}
