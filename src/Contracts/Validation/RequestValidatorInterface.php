<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Validation;

/**
 * Resolves a "request class" (any class-string with a `rules()` method —
 * conventionally a Laravel FormRequest) and validates the current request
 * against it.
 *
 * Replaces `App\Core\Traits\DataArrayFromRequestTrait`. The critical
 * behavioral difference from the original code: {@see validate()} returns
 * ONLY the validated fields (`$validator->validated()`), never the raw
 * `$request->all()`. The original implementation validated the request
 * but then discarded the validation result and returned all input
 * instead — a mass-assignment exposure documented as Bug 4.1 in the
 * Phase 1 audit, fixed unconditionally here per your confirmation.
 */
interface RequestValidatorInterface
{
    /**
     * @param class-string $requestClass A class with a public rules(): array method.
     *
     * @throws \Nexus\CrudEngine\Exceptions\CrudValidationException
     */
    public function validate(string $requestClass): array;
}
