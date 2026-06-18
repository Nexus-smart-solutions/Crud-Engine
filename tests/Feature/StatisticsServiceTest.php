<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\Fixtures\Services\ArticleStatisticsService;
use Nexus\CrudEngine\Tests\TestCase;

final class StatisticsServiceTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_getStatistics_zero_fills_days_with_no_data(): void
    {
        Article::create(['title' => 'Only one', 'created_at' => '2026-02-02 08:00:00']);

        $service = $this->app->make(ArticleStatisticsService::class);

        $results = $service->getStatistics('2026-02-01', '2026-02-03', 'days');

        $this->assertSame(0, $results['2026-02-01']);
        $this->assertSame(1, $results['2026-02-02']);
        $this->assertSame(0, $results['2026-02-03']);
    }

    public function test_getStatistics_caches_results_for_the_configured_ttl(): void
    {
        Article::create(['title' => 'First', 'created_at' => '2026-03-01 08:00:00']);

        $service = $this->app->make(ArticleStatisticsService::class);

        $firstCall = $service->getStatistics('2026-03-01', '2026-03-01', 'days');

        // A second Article created AFTER the first call should not change
        // the cached result for the same query parameters.
        Article::create(['title' => 'Second', 'created_at' => '2026-03-01 09:00:00']);

        $secondCall = $service->getStatistics('2026-03-01', '2026-03-01', 'days');

        $this->assertSame($firstCall, $secondCall);
        $this->assertSame(1, $secondCall['2026-03-01']);
    }
}
