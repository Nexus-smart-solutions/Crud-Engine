<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Validation;

use Illuminate\Http\Request;
use Nexus\CrudEngine\Contracts\Validation\RequestValidatorInterface;
use Nexus\CrudEngine\Exceptions\CrudValidationException;
use Nexus\CrudEngine\Tests\Fixtures\Requests\ArticleRequest;
use Nexus\CrudEngine\Tests\TestCase;

/**
 * Regression test for Bug 4.1 from the Phase 1 audit, fixed
 * unconditionally per your confirmation: the original
 * `DataArrayFromRequestTrait::validatedData()` ran validation but then
 * returned `$request->all()` instead of `$validator->validated()` —
 * meaning any field NOT declared in `rules()` flowed straight into
 * `Model::create()`/`update()` anyway, regardless of validation passing.
 *
 * {@see Nexus\CrudEngine\Tests\Fixtures\Requests\ArticleRequest} only
 * declares `title`, `body`, and `cover_image` — `is_admin` below is
 * deliberately undeclared.
 */
final class LaravelRequestValidatorTest extends TestCase
{
    public function test_validate_returns_only_declared_fields_closing_the_mass_assignment_gap(): void
    {
        $this->app->instance('request', Request::create('/articles', 'POST', [
            'title' => 'Hello world',
            'body' => 'Some body text',
            'is_admin' => true, // not declared in ArticleRequest::rules()
        ]));

        /** @var RequestValidatorInterface $validator */
        $validator = $this->app->make(RequestValidatorInterface::class);

        $data = $validator->validate(ArticleRequest::class);

        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayNotHasKey(
            'is_admin',
            $data,
            'Bug 4.1 regression: an undeclared field leaked through validation.'
        );
    }

    public function test_validate_throws_when_required_fields_are_missing(): void
    {
        $this->app->instance('request', Request::create('/articles', 'POST', [
            'body' => 'Missing the required title',
        ]));

        /** @var RequestValidatorInterface $validator */
        $validator = $this->app->make(RequestValidatorInterface::class);

        $this->expectException(CrudValidationException::class);

        $validator->validate(ArticleRequest::class);
    }
}
