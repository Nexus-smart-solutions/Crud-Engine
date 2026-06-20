# Macros

Five macro classes are registered automatically by `CrudEngineServiceProvider::boot()`. No manual registration or `require` is needed. Each class has a single static `register()` method called during boot.

---

## Blueprint Macros

**Class:** `Nexus\CrudEngine\Macros\BlueprintMacros`

### `Blueprint::status(int $default = 1)`

Adds a `tinyInteger('status')` column with a configurable default.

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$default` | `int` | `1` | The column's database default value |

**Equivalent to:**

```php
$table->tinyInteger('status')->default(1);
```

**Usage in a migration:**

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->status();      // status TINYINT DEFAULT 1
    $table->timestamps();
});

// With a custom default (e.g. inactive by default):
Schema::create('drafts', function (Blueprint $table) {
    $table->id();
    $table->status(0);     // status TINYINT DEFAULT 0
});
```

---

### `Blueprint::standardTime()`

Adds `created_at`, `updated_at`, and `deleted_at` columns in one call.

**No parameters.**

**Equivalent to:**

```php
$table->timestamp('created_at')->useCurrent();
$table->timestamp('updated_at')->nullable();
$table->softDeletes();
```

**Usage:**

```php
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->status();
    $table->standardTime();
});
```

**Note:** `standardTime()` does not call `$table->timestamps()` — it uses `timestamp()` directly and adds `softDeletes()`. Models using this migration should use the `SoftDeletes` trait.

---

## Builder Macros

**Class:** `Nexus\CrudEngine\Macros\BuilderMacros`

Both macros read from `request()` for their dynamic behavior. This is a documented exception to the package's "no global helpers in business logic" rule — query builder macros are bound as closures to the Builder instance, making constructor injection impossible.

---

### `Builder::datesFiltering(string $column = 'created_at')`

Applies a `whereBetween` filter to the query based on `period_type`, `from_date`, and `to_date` request parameters.

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$column` | `string` | `'created_at'` | The date column to filter against |

**Request parameters read:**

| Key | Type | Description |
|---|---|---|
| `period_type` | `string` | One of `'day'`, `'month'`, `'quarter'`, `'year'`, `'range'` |
| `from_date` | `string\|null` | Start of the period (parsed via `Carbon::parseOrNow()`) |
| `to_date` | `string\|null` | End of the period — only used when `period_type = 'range'` |

**Period type behavior:**

| `period_type` | `from` | `to` |
|---|---|---|
| `day` | `startOfDay($from_date)` | `endOfDay($from_date)` |
| `month` | `startOfMonth($from_date)` | `endOfMonth($from_date)` |
| `quarter` | `startOfQuarter($from_date)` | `endOfQuarter($from_date)` |
| `year` | `startOfYear($from_date)` | `endOfYear($from_date)` |
| `range` | `startOfDay($from_date)` | `endOfDay($to_date)` |
| any other / missing | no filter applied | — |

Returns `$this` (the builder) in all cases, so chaining is safe.

**Usage:**

```php
// GET /posts?period_type=month&from_date=2026-03-01
$posts = Post::query()->datesFiltering()->get();

// On a custom date column:
$orders = Order::query()->datesFiltering('ordered_at')->get();

// Chained:
$posts = Post::query()
    ->where('status', 1)
    ->datesFiltering()
    ->customOrdering()
    ->get();
```

---

### `Builder::customOrdering(?string $sortColumn = null, ?string $sort = null)`

Applies an `orderBy` (or `leftJoin` + `orderBy` for relation sorting) based on request parameters or explicit arguments.

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$sortColumn` | `string\|null` | `null` | Explicit column; if null, reads `sortColumn` from request (default: `'id'`) |
| `$sort` | `string\|null` | `null` | `'asc'` or `'desc'`; if null, reads `sort` from request (default: `'desc'`) |

**Security (S2 fix):** The column and sort direction are validated against an allow-list before use:

- Column: must match `/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/` — letters, digits, underscores, and a single dot for relation-column notation
- Sort direction: must be `'asc'` or `'desc'` (case-insensitive)

If either value fails validation, a `Log::warning` is emitted and the builder is returned unchanged (no ordering applied).

**Relation sorting (dot notation):**

When `$sortColumn` contains a dot (e.g. `'category.name'`), a `leftJoin` is applied:

```php
// Sorts posts by category name via a left join
Post::query()->customOrdering('category.name', 'asc')->get();
```

Limitation: Only first-level relation sorting is supported. `'category.subcategory.name'` is rejected by the allow-list (more than one dot).

**Usage:**

```php
// Reads sort params from the request:
Post::query()->datesFiltering()->customOrdering()->get();

// Explicit, not from request:
Post::query()->customOrdering('title', 'asc')->get();

// URL: GET /posts?sortColumn=title&sort=asc
Post::query()->customOrdering()->get();

// Relation sort: GET /posts?sortColumn=category.name&sort=desc
Post::query()->customOrdering()->get();
```

---

## Carbon Macros

**Class:** `Nexus\CrudEngine\Macros\CarbonMacros`

### `Carbon::parseOrNow(string|mixed $date = ''): Carbon`

Parses a date string safely. Returns `Carbon::now()` if the string is empty or cannot be parsed.

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$date` | `mixed` | `''` | A date string to parse |

**Behavior:**

- Empty string or falsy value → `Carbon::now()`
- Valid date string → `Carbon::parse($date)`
- Invalid date string → catches `Carbon\Exceptions\InvalidFormatException` → `Carbon::now()`

**Usage:**

```php
Carbon::parseOrNow('2026-01-15');      // Carbon instance for 2026-01-15
Carbon::parseOrNow('');               // Carbon::now()
Carbon::parseOrNow(null);             // Carbon::now() (falsy)
Carbon::parseOrNow('not a date');     // Carbon::now() (parse exception caught)
```

Used internally by `Builder::datesFiltering()` to parse `from_date` and `to_date` request parameters without ever throwing.

---

## Response Macros

**Class:** `Nexus\CrudEngine\Macros\ResponseMacros`

Both macros delegate to `ResponseFormatterInterface::translate()` for message resolution — they do not implement their own translation logic. This is how the four previously independent `translateMessage()` implementations were collapsed into one.

---

### `Response::success(array $data = [], array $messages = [], int $code = 200): JsonResponse`

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$data` | `array` | `[]` | Response data payload |
| `$messages` | `array` | `[]` | Array of message strings or translation keys |
| `$code` | `int` | `200` | HTTP status code |

**Behavior:** Translates each message through `ResponseFormatterInterface::translate()`. If `$messages` is empty, uses `crud-engine::responses.success.operation_completed` as the default message.

**JSON output:**

```json
{
  "status": "success",
  "messages": ["The record was created successfully."],
  "data": { "id": 1, "title": "My Post" }
}
```

**Usage:**

```php
return response()->success($post->toArray(), ['crud-engine::responses.success.created'], 201);
return response()->success($posts->toArray());
```

---

### `Response::error(string|array $messages = '', int $code = 500): JsonResponse`

**Parameters:**

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$messages` | `string\|array` | `''` | A single message string, an array of strings, or translation keys |
| `$code` | `int` | `500` | HTTP status code |

**Behavior:** Normalizes `$messages` to an array, translates each through `ResponseFormatterInterface::translate()`. An empty message is replaced with `crud-engine::responses.error.server_error`.

**JSON output:**

```json
{
  "status": "error",
  "errors": ["Something went wrong. Please try again."]
}
```

**Usage:**

```php
return response()->error('Custom error message.', 422);
return response()->error(['Field X is required.', 'Field Y is too long.'], 422);
return response()->error();  // uses default server_error message
```

---

## Str Macros

**Class:** `Nexus\CrudEngine\Macros\StrMacros`

### `Str::snakeToTitle(string $value): string`

Converts a snake_case string to Title Case by replacing underscores with spaces and capitalizing each word.

**Implementation:** `ucwords(str_replace('_', ' ', $value))`

| Input | Output |
|---|---|
| `'hello_world'` | `'Hello World'` |
| `'post_created_at'` | `'Post Created At'` |
| `'user_id'` | `'User Id'` |

---

### `Str::humanText(string $value): string`

Converts any string to Title Case human-readable text by replacing all non-alphanumeric runs with a space and applying `Str::title()`.

**Implementation:** `Str::title(preg_replace('/[^a-zA-Z0-9]+/', ' ', $value) ?? '')`

| Input | Output |
|---|---|
| `'hello---world!!'` | `'Hello World'` |
| `'post_created_at'` | `'Post Created At'` |
| `'camelCaseString'` | `'Camelcasestring'` (camelCase is not split) |

**Note:** `humanText` does not split camelCase. For camelCase splitting, use a custom macro or `Str::headline()` (built into Laravel since 8.x).
