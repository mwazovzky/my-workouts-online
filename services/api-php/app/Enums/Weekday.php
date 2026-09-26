<?php

namespace App\Enums;

enum Weekday: string
{
    case Monday = 'Monday';
    case Tuesday = 'Tuesday';
    case Wednesday = 'Wednesday';
    case Thursday = 'Thursday';
    case Friday = 'Friday';
    case Saturday = 'Saturday';
    case Sunday = 'Sunday';

    /**
     * The backing values, in week order.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $day) => $day->value, self::cases());
    }

    /**
     * Carbon's day-of-week index (Sunday = 0 … Saturday = 6).
     */
    public function carbonDayOfWeek(): int
    {
        return match ($this) {
            self::Sunday => 0,
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
        };
    }
}
