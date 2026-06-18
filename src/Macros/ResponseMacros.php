<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Macros;

use Illuminate\Support\Facades\Response;
use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;

/**
 * Replaces `Macros/ReponseMacro.php` (filename typo fixed along the way).
 *
 * The original had its own, fourth, independent copy of "translate this
 * message if it looks like a translation key" — duplicated across three
 * abstract classes plus this macro. Here, both macros delegate to the
 * single {@see ResponseFormatterInterface::translate()} implementation
 * instead of reimplementing the logic, resolved via the container for
 * the same "macro closures aren't container-constructed" reason
 * documented on {@see \Nexus\CrudEngine\Traits\HasFileUrlsTrait}.
 *
 * Default messages now point at the package's own `crud-engine::`
 * translation namespace rather than an application-specific
 * `responses.*` file, per your clarification #2.
 */
final class ResponseMacros
{
    public static function register(): void
    {
        Response::macro('success', function (array $data = [], array $messages = [], int $code = 200) {
            /** @var ResponseFormatterInterface $formatter */
            $formatter = app(ResponseFormatterInterface::class);

            $translated = array_map(static fn ($message) => $formatter->translate((string) $message), $messages);

            if ($translated === []) {
                $translated = [$formatter->translate('crud-engine::responses.success.operation_completed')];
            }

            /** @var \Illuminate\Routing\ResponseFactory $this */
            return $this->json([
                'status' => 'success',
                'messages' => $translated,
                'data' => $data,
            ], $code);
        });

        Response::macro('error', function (string|array $messages = '', int $code = 500) {
            /** @var ResponseFormatterInterface $formatter */
            $formatter = app(ResponseFormatterInterface::class);

            $messagesArray = is_array($messages) ? $messages : [$messages];

            $translated = array_map(static function ($message) use ($formatter) {
                if (empty($message)) {
                    return $formatter->translate('crud-engine::responses.error.server_error');
                }

                return $formatter->translate((string) $message);
            }, $messagesArray);

            /** @var \Illuminate\Routing\ResponseFactory $this */
            return $this->json([
                'status' => 'error',
                'errors' => $translated,
            ], $code);
        });
    }
}
