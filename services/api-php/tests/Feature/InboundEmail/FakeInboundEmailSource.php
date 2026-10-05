<?php

declare(strict_types=1);

namespace Tests\Feature\InboundEmail;

use App\Services\InboundEmail\InboundEmail;
use App\Services\InboundEmail\InboundEmailSourceInterface;

final class FakeInboundEmailSource implements InboundEmailSourceInterface
{
    /**
     * @var array<string, InboundEmail>
     */
    public array $emails = [];

    public int $lookups = 0;

    public function find(string $id): InboundEmail
    {
        $this->lookups++;

        return $this->emails[$id];
    }
}
