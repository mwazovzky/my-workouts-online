<?php

namespace Tests\Unit\Enums;

use App\Enums\FieldErrorCode;
use App\Exceptions\Api\ApiErrorResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldErrorCodeTest extends TestCase
{
    /**
     * @return array<string, array{string, array<int, string>, FieldErrorCode}>
     */
    public static function rules(): array
    {
        return [
            'required' => ['required', [], FieldErrorCode::Required],
            'present' => ['present', [], FieldErrorCode::Required],
            'email' => ['email', [], FieldErrorCode::InvalidFormat],
            'password rule' => ['password', [], FieldErrorCode::InvalidFormat],
            'enum' => ['enum', [], FieldErrorCode::InvalidValue],
            'exists' => ['exists', [], FieldErrorCode::NotFound],
            'min on string' => ['min', ['required', 'string', 'min:3'], FieldErrorCode::TooShort],
            'min on array' => ['min', ['array', 'min:1'], FieldErrorCode::TooShort],
            'min on integer' => ['min', ['integer', 'min:1'], FieldErrorCode::OutOfRange],
            'min undeclared' => ['min', [], FieldErrorCode::OutOfRange],
            'max on string' => ['max', ['string', 'max:255'], FieldErrorCode::TooLong],
            'max on numeric' => ['max', ['numeric', 'max:10000'], FieldErrorCode::OutOfRange],
            'between' => ['between', ['integer'], FieldErrorCode::OutOfRange],
            'heart rate zone' => ['heart_rate_zone_within_range', [], FieldErrorCode::OutOfRange],
            'unique' => ['unique', [], FieldErrorCode::NotUnique],
            'distinct' => ['distinct', [], FieldErrorCode::NotUnique],
            'confirmed' => ['confirmed', [], FieldErrorCode::Mismatch],
            'current password' => ['current_password', [], FieldErrorCode::Mismatch],
            'unknown field' => ['unknown_field', [], FieldErrorCode::UnknownField],
            'unmapped rule' => ['some_future_rule', [], FieldErrorCode::InvalidValue],
        ];
    }

    /**
     * @param  array<int, mixed>  $declared
     */
    #[Test]
    #[DataProvider('rules')]
    public function maps_a_rule_to_a_field_error_code(string $rule, array $declared, FieldErrorCode $expected): void
    {
        $this->assertSame($expected, FieldErrorCode::fromRule($rule, $declared));
    }

    #[Test]
    public function dotted_paths_become_json_pointers_with_escaping(): void
    {
        $this->assertSame('/email', ApiErrorResponse::pointer('email'));
        $this->assertSame('/activities/0/sets/1/effort_value', ApiErrorResponse::pointer('activities.0.sets.1.effort_value'));
        $this->assertSame('/a~1b/c~0d', ApiErrorResponse::pointer('a/b.c~d'));
    }
}
