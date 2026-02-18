<?php

namespace Tests\Unit\Enums;

use App\Enums\DifficultyUnit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DifficultyUnitTest extends TestCase
{
    #[Test]
    #[DataProvider('labelProvider')]
    public function it_returns_the_correct_label(DifficultyUnit $unit, string $expected): void
    {
        $this->assertSame($expected, $unit->label());
    }

    /** @return array<string, array{DifficultyUnit, string}> */
    public static function labelProvider(): array
    {
        return [
            'kilograms' => [DifficultyUnit::Kilograms, 'kg'],
            'pounds' => [DifficultyUnit::Pounds, 'lbs'],
            'plates' => [DifficultyUnit::Plates, 'Plates'],
            'none' => [DifficultyUnit::None, ''],
        ];
    }

    #[Test]
    #[DataProvider('columnLabelProvider')]
    public function it_returns_the_correct_column_label(DifficultyUnit $unit, string $expected): void
    {
        $this->assertSame($expected, $unit->columnLabel());
    }

    /** @return array<string, array{DifficultyUnit, string}> */
    public static function columnLabelProvider(): array
    {
        return [
            'kilograms' => [DifficultyUnit::Kilograms, 'Weight (kg)'],
            'pounds' => [DifficultyUnit::Pounds, 'Weight (lbs)'],
            'plates' => [DifficultyUnit::Plates, 'Plates'],
            'none' => [DifficultyUnit::None, ''],
        ];
    }
}
