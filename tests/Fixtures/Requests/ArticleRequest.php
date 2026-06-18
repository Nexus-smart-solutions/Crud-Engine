<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Fixtures\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deliberately does NOT declare every field a malicious/careless client
 * might send (e.g. no `is_admin` rule) — this is the exact shape needed
 * to regression-test Bug 4.1: under the original buggy behavior,
 * validating against these rules but returning `$request->all()` would
 * let an undeclared `is_admin` field flow straight into `Model::create()`.
 */
class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'cover_image' => ['nullable'],
        ];
    }
}
