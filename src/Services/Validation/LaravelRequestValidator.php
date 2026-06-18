<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Validation;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\Request;
use Nexus\CrudEngine\Contracts\Validation\RequestValidatorInterface;
use Nexus\CrudEngine\Exceptions\CrudValidationException;

/**
 * Replaces `App\Core\Traits\DataArrayFromRequestTrait`.
 *
 * Fixes Bug 4.1 from the Phase 1 audit unconditionally, per your
 * confirmation: the original trait ran validation via the resolved
 * request class's `rules()`, but then returned `$request->all()` instead
 * of the validator's result — meaning any field the client sent, even
 * ones never declared in `rules()`, flowed straight into
 * `Model::create()`/`update()`. {@see validate()} here always returns
 * `$validator->validated()` and nothing else.
 *
 * Both collaborators are constructor-injected (Laravel's own validation
 * factory and the current request), not resolved via the `Validator::make()`
 * facade or the `request()` helper — this is what makes the service
 * usable outside an HTTP context in tests.
 */
final class LaravelRequestValidator implements RequestValidatorInterface
{
    public function __construct(
        private readonly ValidationFactory $validationFactory,
        private readonly Request $request,
        private readonly \Illuminate\Contracts\Container\Container $container,
    ) {
    }

    public function validate(string $requestClass): array
    {
        $rules = $this->resolveRules($requestClass);

        $validator = $this->validationFactory->make($this->request->all(), $rules);

        if ($validator->fails()) {
            throw new CrudValidationException($validator);
        }

        return $validator->validated();
    }

    private function resolveRules(string $requestClass): array
    {
        if (! method_exists($requestClass, 'rules')) {
            throw new \InvalidArgumentException(
                "Request class [{$requestClass}] must define a public rules(): array method."
            );
        }

        // Resolved through the container (not `new`) so a FormRequest with
        // its own constructor dependencies, or one that relies on route
        // binding inside rules(), behaves the same as it would if Laravel
        // had resolved it directly for a controller method.
        $instance = $this->container->make($requestClass);

        return $instance->rules();
    }
}
