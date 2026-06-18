<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

/**
 * Parameters for a single time-bucketed aggregate statistics query,
 * consumed by {@see \Nexus\CrudEngine\Contracts\Statistics\StatisticsQueryStrategyInterface}.
 *
 * @param class-string $modelClass
 * @param string $interval One of 'days', 'months', 'years'.
 * @param string[] $scopes Named local scopes to apply to the query.
 * @param string[] $allowedFilters Filter names allowed when the Spatie strategy is used.
 */
final readonly class StatisticsQuery
{
    public function __construct(
        public string $modelClass,
        public string $dateColumn,
        public ?string $sumColumn,
        public string $startDate,
        public string $endDate,
        public string $interval,
        public array $scopes = [],
        public array $allowedFilters = [],
    ) {
    }
}
