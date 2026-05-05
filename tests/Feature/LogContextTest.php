<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LogContextTest extends TestCase
{
    #[Test]
    public function it_uses_valid_uuid_from_x_request_id_header(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        Log::shouldReceive('shareContext')
            ->once()
            ->withArgs(fn (array $context) => $context['request_id'] === $uuid);

        $this->get('/', ['X-Request-Id' => $uuid]);
    }

    #[Test]
    public function it_generates_uuid_when_header_is_absent(): void
    {
        Log::shouldReceive('shareContext')
            ->once()
            ->withArgs(fn (array $context) => Str::isUuid($context['request_id']));

        $this->get('/');
    }

    #[Test]
    public function it_generates_uuid_when_header_is_not_a_valid_uuid(): void
    {
        Log::shouldReceive('shareContext')
            ->once()
            ->withArgs(fn (array $context) => Str::isUuid($context['request_id']));

        $this->get('/', ['X-Request-Id' => 'not-a-uuid-'.str_repeat('x', 200)]);
    }
}
