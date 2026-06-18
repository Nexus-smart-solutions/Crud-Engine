<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Macros;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Replaces `Macros/BuilderMacro.php`.
 *
 * `request()` is used directly inside these macro closures (rather than
 * injected) for the same reason documented on
 * {@see \Nexus\CrudEngine\Traits\HasFileUrlsTrait}: a query builder
 * macro closure is bound by Laravel as a method on the Builder instance
 * itself ($this === the builder), not resolved by the container, so
 * constructor injection isn't an option here either. This is framework
 * integration glue, not domain logic.
 *
 * Two fixes from the Phase 1 audit applied to `customOrdering()`:
 *  - Security Finding S2: the sort column and relation name are now
 *    validated against a strict `[A-Za-z0-9_.]` allow-list before being
 *    used in `orderBy()`/`leftJoin()`. The original passed
 *    `request()->sortColumn` straight through with no validation at all.
 *  - The empty `catch` block that silently swallowed every exception is
 *    replaced with a logged warning, so a malformed sort request is
 *    visible in production instead of failing invisibly.
 */
final class BuilderMacros
{
    public static function register(): void
    {
        Builder::macro('datesFiltering', function (string $column = 'created_at') {
            /** @var Builder $this */
            $periodType = request()['period_type'] ?? null;
            $fromDate = request()['from_date'] ?? null;
            $toDate = request()['to_date'] ?? null;

            [$from, $to] = match ($periodType) {
                'day' => [Carbon::parseOrNow($fromDate)->startOfDay(), Carbon::make($fromDate)?->endOfDay()],
                'month' => [Carbon::parseOrNow($fromDate)->startOfMonth(), Carbon::make($fromDate)?->endOfMonth()],
                'quarter' => [Carbon::parseOrNow($fromDate)->startOfQuarter(), Carbon::make($fromDate)?->endOfQuarter()],
                'year' => [Carbon::parseOrNow($fromDate)->startOfYear(), Carbon::make($fromDate)?->endOfYear()],
                'range' => [Carbon::parseOrNow($fromDate)->startOfDay(), Carbon::parseOrNow($toDate)->endOfDay()],
                default => [null, null],
            };

            if ($from === null || $to === null) {
                return $this;
            }

            return $this->whereBetween($column, [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
        });

        Builder::macro('customOrdering', function (?string $sortColumn = null, ?string $sort = null) {
            /** @var Builder $this */
            try {
                $requestSortColumn = $sortColumn ?? request()->input('sortColumn', 'id');
                $requestSort = $sort ?? request()->input('sort', 'desc');

                if (! self::isSafeIdentifierPath($requestSortColumn) || ! in_array(strtolower($requestSort), ['asc', 'desc'], true)) {
                    Log::warning('crud-engine: ignored unsafe customOrdering input', [
                        'sortColumn' => $requestSortColumn,
                        'sort' => $requestSort,
                    ]);

                    return $this;
                }

                // Works only with first-level relationships, matching the
                // original implementation's documented limitation.
                if (str_contains($requestSortColumn, '.')) {
                    [$relation, $column] = explode('.', $requestSortColumn, 2);
                    $relationModel = $this->getModel()->{$relation}();
                    $relationTable = $relationModel->getModel()->getTable();
                    $relationForeignKey = $relationModel->getForeignKeyName();
                    $queryTable = $this->from;

                    return $this->leftJoin($relationTable, "{$queryTable}.{$relationForeignKey}", '=', "{$relationTable}.id")
                        ->orderBy("{$relationTable}.{$column}", $requestSort);
                }

                return $this->orderBy($requestSortColumn, $requestSort);
            } catch (\Throwable $exception) {
                Log::warning('crud-engine: customOrdering failed, returning unsorted query', [
                    'message' => $exception->getMessage(),
                ]);
            }

            return $this;
        });
    }

    /**
     * Allow-lists a column or "relation.column" path to letters, digits,
     * and underscores only — closes the original's unrestricted pass-
     * through of client-supplied sort input into orderBy()/leftJoin().
     */
    private static function isSafeIdentifierPath(string $value): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $value);
    }
}
