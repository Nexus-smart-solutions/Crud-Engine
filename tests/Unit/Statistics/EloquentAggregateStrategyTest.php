<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Statistics;

use Nexus\CrudEngine\DTOs\StatisticsQuery;
use Nexus\CrudEngine\Strategies\Statistics\EloquentAggregateStrategy;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\TestCase;

/**
 * The original `AbstractStatisticsRowsCounted` hardcoded MySQL's
 * `DATE_FORMAT()` syntax directly into raw SQL. This strategy is pure
 * Eloquent + PHP-side grouping specifically so it also passes against
 * SQLite (used here) and Postgres, not just MySQL.
 */
final class EloquentAggregateStrategyTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_counts_rows_grouped_by_day(): void
    {
        Article::create(['title' => 'A', 'created_at' => '2026-01-01 10:00:00']);
        Article::create(['title' => 'B', 'created_at' => '2026-01-01 12:00:00']);
        Article::create(['title' => 'C', 'created_at' => '2026-01-02 09:00:00']);

        $strategy = new EloquentAggregateStrategy();

        $results = $strategy->execute(new StatisticsQuery(
            modelClass: Article::class,
            dateColumn: 'created_at',
            sumColumn: null,
            startDate: '2026-01-01',
            endDate: '2026-01-02',
            interval: 'days',
        ));

        $this->assertSame(2, $results['2026-01-01']);
        $this->assertSame(1, $results['2026-01-02']);
    }

    public function test_returns_empty_array_when_no_rows_match(): void
    {
        $strategy = new EloquentAggregateStrategy();

        $results = $strategy->execute(new StatisticsQuery(
            modelClass: Article::class,
            dateColumn: 'created_at',
            sumColumn: null,
            startDate: '2026-01-01',
            endDate: '2026-01-02',
            interval: 'days',
        ));

        $this->assertSame([], $results);
    }
}
