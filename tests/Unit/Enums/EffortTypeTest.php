<?php

namespace Tests\Unit\Enums;

use App\Enums\EffortType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EffortTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('labelProvider')]
    public function it_returns_the_correct_label(EffortType $type, string $expected): void
    {
        $this->assertSame($expected, $type->label());
    }

    /** @return array<string, array{EffortType, string}> */
    public static function labelProvider(): array
    {
        return [
            'repetitions' => [EffortType::Repetitions, 'reps'],
            'duration' => [EffortType::Duration, 'sec'],
        ];
    }

    #[Test]
    #[DataProvider('columnLabelProvider')]
    public function it_returns_the_correct_column_label(EffortType $type, string $expected): void
    {
        $this->assertSame($expected, $type->columnLabel());
    }

    /** @return array<string, array{EffortType, string}> */
    public static function columnLabelProvider(): array
    {
        return [
            'repetitions' => [EffortType::Repetitions, 'Reps'],
            'duration' => [EffortType::Duration, 'Duration'],
        ];
    }
}
