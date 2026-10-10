<?php

namespace App\Enums;

/**
 * The `code` of one entry in a validation Problem's `errors` list. Mirrors the
 * `FieldErrorCode` enum in `contracts/openapi.yaml`; Laravel rule names are
 * mapped here so the wire format never exposes them.
 */
enum FieldErrorCode: string
{
    case Required = 'required';
    case InvalidFormat = 'invalid_format';
    case InvalidValue = 'invalid_value';
    case TooShort = 'too_short';
    case TooLong = 'too_long';
    case OutOfRange = 'out_of_range';
    case NotUnique = 'not_unique';
    case NotFound = 'not_found';
    case Mismatch = 'mismatch';
    case UnknownField = 'unknown_field';

    /**
     * @param  string  $rule  the failed rule in snake_case (`required`, `min`, `heart_rate_zone_within_range`)
     * @param  array<int, mixed>  $declaredRules  every rule declared for the field, used to tell a length bound from a numeric one
     */
    public static function fromRule(string $rule, array $declaredRules = []): self
    {
        return match ($rule) {
            'required', 'required_if', 'required_unless', 'required_with', 'required_without',
            'required_without_all', 'present', 'filled', 'accepted' => self::Required,
            'email', 'string', 'integer', 'numeric', 'boolean', 'array', 'date', 'url', 'uuid',
            'lowercase', 'regex', 'json', 'associative_array', 'password' => self::InvalidFormat,
            'in', 'enum', 'not_in', 'prohibited', 'completed_set_requires_effort' => self::InvalidValue,
            'exists' => self::NotFound,
            'min' => self::isLengthBound($declaredRules) ? self::TooShort : self::OutOfRange,
            'max' => self::isLengthBound($declaredRules) ? self::TooLong : self::OutOfRange,
            'between', 'size', 'digits', 'heart_rate_zone_within_range' => self::OutOfRange,
            'unique', 'distinct' => self::NotUnique,
            'confirmed', 'current_password', 'same' => self::Mismatch,
            'unknown_field' => self::UnknownField,
            default => self::InvalidValue,
        };
    }

    /**
     * `min`/`max` bound a length when the field is a string or an array, a
     * magnitude otherwise.
     *
     * @param  array<int, mixed>  $declaredRules
     */
    private static function isLengthBound(array $declaredRules): bool
    {
        foreach ($declaredRules as $declared) {
            if (is_string($declared) && in_array(strtolower(explode(':', $declared, 2)[0]), ['string', 'array'], true)) {
                return true;
            }
        }

        return false;
    }
}
