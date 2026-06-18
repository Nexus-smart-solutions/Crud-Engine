<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Services\Responses;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\JsonResponse;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\DTOs\CrudOperationResult;

/**
 * Default response formatter, reproducing the original
 * `{status, messages, data, code}` envelope.
 *
 * This is the single implementation of "translate this message if it
 * looks like a translation key" — the original codebase had this exact
 * logic duplicated four times (three abstract classes plus the Response
 * macro). All package translation keys live under the `crud-engine::`
 * namespace, shipped with English and Arabic defaults, fully overridable
 * by publishing (see resources/lang).
 */
final class JsonResponseFormatter implements ResponseFormatterInterface
{
    public function __construct(private readonly Translator $translator)
    {
    }

    public function format(CrudOperationResult $result): JsonResponse
    {
        $payload = $result->toArray();
        $payload['messages'] = array_map($this->translate(...), $result->messages);

        return new JsonResponse($payload, $result->code);
    }

    public function translate(string $message): string
    {
        if (! str_contains($message, '.')) {
            return $message;
        }

        $translated = $this->translator->get($message);

        // Illuminate\Translation\Translator::get() returns the key itself
        // when no translation line is found — fall back to the raw
        // message in that case rather than echoing the dotted key back.
        return $translated === $message ? $message : $translated;
    }
}
