<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\DTOs;

use Nexus\CrudEngine\DTOs\Enums\OperationStatus;

/**
 * Replaces the raw, untyped associative arrays
 * (`['status' => ..., 'messages' => ..., 'data' => ..., 'code' => ...]`)
 * returned by every Crud operation in the original codebase.
 *
 * Plain data holder — no closures, no container references, no open
 * resources — so it survives queue-job serialization safely.
 */
final readonly class CrudOperationResult
{
    /**
     * @param string[] $messages
     * @param array<int, int|string> $failedIds
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public OperationStatus $status,
        public array $messages = [],
        public array $data = [],
        public int $code = 200,
        public array $failedIds = [],
        public array $meta = [],
    ) {
    }

    public static function success(array $data = [], array $messages = [], int $code = 200, array $meta = []): self
    {
        return new self(OperationStatus::Success, $messages, $data, $code, [], $meta);
    }

    public static function partialSuccess(array $messages, array $failedIds, int $code = 207, array $meta = []): self
    {
        return new self(OperationStatus::PartialSuccess, $messages, [], $code, $failedIds, $meta);
    }

    public static function error(array $messages, int $code = 500, array $meta = []): self
    {
        return new self(OperationStatus::Error, $messages, [], $code, [], $meta);
    }

    public function isSuccessful(): bool
    {
        return $this->status === OperationStatus::Success;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'status' => $this->status->value,
            'messages' => $this->messages,
            'data' => $this->data,
            'code' => $this->code,
        ];

        if ($this->failedIds !== []) {
            $payload['failed_ids'] = $this->failedIds;
        }

        if ($this->meta !== []) {
            $payload['meta'] = $this->meta;
        }

        return $payload;
    }
}
