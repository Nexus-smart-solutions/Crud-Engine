<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nexus\CrudEngine\Tests\Fixtures\CreatesFixtureSchema;
use Nexus\CrudEngine\Tests\Fixtures\Models\Article;
use Nexus\CrudEngine\Tests\TestCase;

final class MacrosTest extends TestCase
{
    use CreatesFixtureSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createFixtureSchema();
    }

    public function test_blueprint_status_and_standard_time_macros_create_expected_columns(): void
    {
        Schema::create('macro_probe', function ($table) {
            $table->id();
            $table->status();
            $table->standardTime();
        });

        $this->assertTrue(Schema::hasColumns('macro_probe', ['status', 'created_at', 'updated_at', 'deleted_at']));
    }

    public function test_carbon_parse_or_now_falls_back_on_invalid_input(): void
    {
        $valid = Carbon::parseOrNow('2026-01-01');
        $this->assertSame('2026-01-01', $valid->format('Y-m-d'));

        $fallback = Carbon::parseOrNow('not a real date');
        $this->assertTrue($fallback->isToday());
    }

    public function test_str_snake_to_title_and_human_text(): void
    {
        $this->assertSame('Hello World', Str::snakeToTitle('hello_world'));
        $this->assertSame('Hello World', Str::humanText('hello---world!!'));
    }

    public function test_builder_custom_ordering_with_a_safe_column(): void
    {
        Article::create(['title' => 'B']);
        Article::create(['title' => 'A']);

        $results = Article::query()->customOrdering('title', 'asc')->pluck('title')->all();

        $this->assertSame(['A', 'B'], $results);
    }

    /**
     * Regression test for Security Finding S2: an unsafe sort column
     * must be ignored (falling back to the query's natural order)
     * rather than being interpolated into orderBy()/leftJoin().
     */
    public function test_builder_custom_ordering_ignores_an_unsafe_column(): void
    {
        Article::create(['title' => 'Z']);

        $query = Article::query()->customOrdering('title; DROP TABLE articles', 'asc');

        // Should not throw, and should not have applied any ordering
        // clause derived from the unsafe input.
        $this->assertSame(1, $query->count());
    }

    public function test_builder_dates_filtering_with_a_range(): void
    {
        Article::create(['title' => 'In range', 'created_at' => '2026-01-15 00:00:00']);
        Article::create(['title' => 'Out of range', 'created_at' => '2026-05-01 00:00:00']);

        $this->app->instance('request', Request::create('/', 'GET', [
            'period_type' => 'range',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
        ]));

        $results = Article::query()->datesFiltering()->pluck('title')->all();

        $this->assertContains('In range', $results);
        $this->assertNotContains('Out of range', $results);
    }

    public function test_response_success_macro_returns_the_standard_envelope(): void
    {
        $response = Response::success(['id' => 1], ['Done.']);

        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(['Done.'], $payload['messages']);
    }

    public function test_response_error_macro_uses_the_default_message_when_empty(): void
    {
        $response = Response::error('');

        $payload = $response->getData(true);

        $this->assertSame('error', $payload['status']);
        $this->assertNotEmpty($payload['errors'][0]);
    }
}
