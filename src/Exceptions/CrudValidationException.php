<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Exceptions;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\DTOs\CrudOperationResult;

/**
 * Replaces the application-specific `App\Exceptions\CustomValidationException`
 * referenced (but not defined) in the original codebase.
 *
 * Implements Laravel's {@see Responsable} contract so it renders itself
 * into the package's standard error envelope if it ever escapes uncaught
 * — no custom exception-handler registration required in the consuming
 * application.
 *
 * Resolving the formatter via the container inside {@see toResponse()} is
 * a deliberate, narrow exception to this package's "no app()/container
 * pulls in business logic" rule: Laravel invokes `toResponse()` directly
 * with only a Request argument, so constructor injection isn't possible
 * here — this is framework integration glue, not domain logic.
 */
class CrudValidationException extends CrudEngineException implements Responsable
{
    public function __construct(private readonly Validator $validator)
    {
        parent::__construct('The given data was invalid.');
    }

    public function errors(): array
    {
        return $this->validator->errors()->toArray();
    }

    public function validator(): Validator
    {
        return $this->validator;
    }

    public function toResponse(Request $request): JsonResponse
    {
        /** @var ResponseFormatterInterface $formatter */
        $formatter = app(ResponseFormatterInterface::class);

        $messages = collect($this->errors())->flatten()->all();

        return $formatter->format(CrudOperationResult::error($messages, 422, [
            'errors' => $this->errors(),
        ]));
    }
}
