<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Strategies\Statistics;

use Illuminate\Support\Carbon;
use Nexus\CrudEngine\Contracts\Statistics\StatisticsQueryStrategyInterface;
use Nexus\CrudEngine\DTOs\StatisticsQuery;
use Nexus\CrudEngine\Exceptions\CrudEngineException;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Optional statistics query engine backed by `spatie/laravel-query-builder`,
 * preserved for applications that already rely on its filter/sort
 * conventions for statistics endpoints.
 *
 * `spatie/laravel-query-builder` is a Composer `suggest`, never a
 * `require`, of this package (per your clarification #7). The service
 * provider only binds this class when the package detects the dependency
 * is actually installed (`class_exists()` guard); the constructor guard
 * below is defense-in-depth in case this class is ever instantiated
 * directly instead of resolved from the container.
 */
final class SpatieQueryBuilderStrategy implements StatisticsQueryStrategyInterface
{
    public function __construct()
    {
        if (! class_exists(QueryBuilder::class)) {
            throw new CrudEngineException(
                'The Spatie statistics strategy requires spatie/laravel-query-builder. '.
                'Run "composer require spatie/laravel-query-builder" or use the default '.
                'EloquentAggregateStrategy instead.'
            );
        }
    }

    public function execute(StatisticsQuery $query): array
    {
        $builder = QueryBuilder::for($query->modelClass)
            ->allowedFilters($query->allowedFilters)
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
