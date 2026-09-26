<?php

namespace Tests\Unit\Enums;

use App\Enums\Weekday;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WeekdayTest extends TestCase
{
    #[Test]
    public function values_returns_all_seven_days_in_order(): void
    {
        $this->assertSame(
            ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            Weekday::values(),
        );
    }

    #[Test]
    public function carbon_day_of_week_matches_carbon_indexing(): void
    {
        $this->assertSame(0, Weekday::Sunday->carbonDayOfWeek());
        $this->assertSame(1, Weekday::Monday->carbonDayOfWeek());
        $this->assertSame(6, Weekday::Saturday->carbonDayOfWeek());
    }
}
