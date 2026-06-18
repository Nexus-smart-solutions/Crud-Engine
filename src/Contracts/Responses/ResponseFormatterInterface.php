<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Contracts\Responses;

use Illuminate\Http\JsonResponse;
use Nexus\CrudEngine\DTOs\CrudOperationResult;

/**
 * Builds the outward-facing response envelope for a Crud operation result.
 *
 * The default implementation reproduces the original `{status, messages,
 * data, code}` JSON envelope, including translation-key resolution. This
 * is also the single place that logic now lives — the original codebase
 * had four independent copies of "translate this message if it looks
 * like a translation key" spread across three abstract classes and a
 * Response macro.
 */
interface ResponseFormatterInterface
{
    public function format(CrudOperationResult $result): JsonResponse;

    /**
     * Translate a message if it looks like a translation key (contains a
     * dot), otherwise return it unchanged.
     */
    public function translate(string $message): string;
}
