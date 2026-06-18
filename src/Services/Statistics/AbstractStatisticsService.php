<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Statistics;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use Nexus\CrudEngine\Contracts\Statistics\StatisticsQueryStrategyInterface;
use Nexus\CrudEngine\DTOs\StatisticsQuery;

/**
 * Replaces `App\Core\Statistics\AbstractStatisticsRowsCounted`.
 *
 * Differences from the original:
 *  - The actual query execution is delegated to an injected
 *    {@see StatisticsQueryStrategyInterface}, so this class has zero
 *    knowledge of whether Spatie's query builder is installed, and zero
 *    raw-SQL string interpolation (the original interpolated
 *    `$dateColumn`/`$sumColumn` directly into `DATE_FORMAT()`/`SUM()`).
 *  - Results are cached (TTL from `crud-engine.statistics.cache_ttl`),
 *    addressing the Phase 1 finding that statistics queries re-ran on
 *    every request with no caching layer.
 *  - Date-range inputs are constructor/method parameters, not read from
 *    `request()` directly, so this class works the same from a
 *    controller, a console command, or a queued job.
 */
abstract class AbstractStatisticsService
{
    public function __construct(
        private readonly StatisticsQueryStrategyInterface $queryStrategy,
        private readonly CacheRepository $cache,
        private readonly int $cacheTtlSeconds,
    ) {
    }

    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    abstract public function getModelClass(): string;

    abstract public function getDateColumn(): string;

    /**
     * Null means "count rows" rather than "sum a column."
     */
    public function getSumColumn(): ?string
    {
        return null;
    }

    /**
     * @return string[] Local scope method names to apply, e.g. ['active'].
     */
    public function getScopes(): array
    {
        return [];
    }

    /**
     * @return string[] Filter names allowed when the Spatie strategy is bound.
     */
    public function getAllowedFilters(): array
    {
        return [];
    }

    /**
     * Returns a zero-filled bucket for every day/month/year in the range,
     * with real aggregate values merged in where data exists — matching
     * the original's "always show every bucket, even empty ones" output
     * shape, but computed via the injected strategy instead of inline SQL.
     *
     * @param string $interval One of 'days', 'months', 'years'.
     *
     * @return array<string, float|int>
     */
    public function getStatistics(string $startDate, string $endDate, string $interval = 'days'): array
    {
        $query = new StatisticsQuery(
            modelClass: $this->getModelClass(),
            dateColumn: $this->getDateColumn(),
            sumColumn: $this->getSumColumn(),
            startDate: $startDate,
            endDate: $endDate,
            interval: $interval,
            scopes: $this->getScopes(),
            allowedFilters: $this->getAllowedFilters(),
        );

        $cacheKey = $this->cacheKey($query);

        return $this->cache->remember($cacheKey, $this->cacheTtlSeconds, function () use ($query) {
            $actual = $this->queryStrategy->execute($query);

            return $this->fillEmptyBuckets($query, $actual);
        });
    }

    private function cacheKey(StatisticsQuery $query): string
    {
        return implode(':', [
            'crud-engine-stats',
            str_replace('\\', '_', $query->modelClass),
            $query->dateColumn,
            $query->sumColumn ?? 'count',
            $query->startDate,
            $query->endDate,
            $query->interval,
        ]);
    }

    /**
     * @param array<string, float|int> $actual
     *
     * @return array<string, float|int>
     */
    private function fillEmptyBuckets(StatisticsQuery $query, array $actual): array
    {
        $start = Carbon::parse($query->startDate);
        $end = Carbon::parse($query->endDate);

        $buckets = [];
        $cursor = $start->copy();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = match ($query->interval) {
                'years' => $cursor->format('Y'),
                'months' => $cursor->format('Y-m'),
                default => $cursor->format('Y-m-d'),
            };

            $buckets[$key] = $actual[$key] ?? 0;

            $cursor = match ($query->interval) {
                'years' => $cursor->addYear(),
                'months' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }
}
