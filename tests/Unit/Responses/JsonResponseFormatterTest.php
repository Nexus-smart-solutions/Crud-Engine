<?php

declare(strict_types=1);

namespace Nexus\CrudEngine\Tests\Unit\Responses;

use Nexus\CrudEngine\Contracts\Responses\ResponseFormatterInterface;
use Nexus\CrudEngine\DTOs\CrudOperationResult;
use Nexus\CrudEngine\Tests\TestCase;

final class JsonResponseFormatterTest extends TestCase
{
    public function test_format_produces_the_standard_envelope(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);

        $result = CrudOperationResult::success(['id' => 1], ['Created.'], 201);

        $response = $formatter->format($result);

        $payload = $response->getData(true);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('success', $payload['status']);
        $this->assertSame(['Created.'], $payload['messages']);
        $this->assertSame(['id' => 1], $payload['data']);
    }

    public function test_translate_returns_plain_messages_unchanged(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);

        $this->assertSame('Just a plain message', $formatter->translate('Just a plain message'));
    }

    public function test_translate_resolves_a_dotted_key_using_package_translations(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);

        $translated = $formatter->translate('crud-engine::responses.success.created');

        $this->assertNotSame('crud-engine::responses.success.created', $translated);
    }

    public function test_translate_falls_back_to_the_raw_key_when_no_translation_exists(): void
    {
        $formatter = $this->app->make(ResponseFormatterInterface::class);

        $translated = $formatter->translate('crud-engine::responses.does_not_exist.key');

        $this->assertSame('crud-engine::responses.does_not_exist.key', $translated);
    }
}
