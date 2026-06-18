<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Statistics;

use Illuminate\Support\Carbon;
use Nexus\CrudEngine\Contracts\Statistics\StatisticsQueryStrategyInterface;
use Nexus\CrudEngine\DTOs\StatisticsQuery;

/**
 * Default statistics query engine: pure Eloquent, no third-party
 * dependency, portable across MySQL/Postgres/SQLite (the original
 * `AbstractStatisticsRowsCounted` hardcoded MySQL's `DATE_FORMAT()`
 * syntax, which doesn't exist on Postgres or SQLite).
 *
 * Groups rows in PHP rather than relying on a database-specific date
 * formatting function, trading a small amount of query simplicity for
 * portability — appropriate for a package meant to install into "any
 * Laravel application."
 */
final class EloquentAggregateStrategy implements StatisticsQueryStrategyInterface
{
    public function execute(StatisticsQuery $query): array
    {
        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $query->modelClass();

        $builder = $instance->query()
            ->whereBetween($query->dateColumn, [$query->startDate, $query->endDate]);

        foreach ($query->scopes as $scope) {
            $builder->{$scope}();
        }

        $rows = $builder->get([$query->dateColumn, ...($query->sumColumn ? [$query->sumColumn] : [])]);

        $results = [];

        foreach ($rows as $row) {
            $bucket = $this->bucketKey(Carbon::parse($row->{$query->dateColumn}), $query->interval);

            $value = $query->sumColumn !== null ? (float) $row->{$query->sumColumn} : 1;

            $results[$bucket] = ($results[$bucket] ?? 0) + $value;
        }

        return $results;
    }

    private function bucketKey(Carbon $date, string $interval): string
    {
        return match ($interval) {
            'years' => $date->format('Y'),
            'months' => $date->format('Y-m'),
            default => $date->format('Y-m-d'),
        };
    }
}
