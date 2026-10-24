<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

/**
 * Base class for every API request: rejects input keys the request does not
 * declare, so a client typo fails loudly instead of being ignored. Mirrors
 * `additionalProperties: false` on the request schemas in the contract; for
 * GET and HEAD the declared input is the query string.
 *
 * It also applies each string `max:N` rule to the JSON body as sent. The
 * TrimStrings middleware trims input before validation, so " " plus 255
 * characters would otherwise pass `max:255`; the contract's `maxLength` and
 * the Go API count the value as sent. Stored values stay trimmed.
 */
abstract class ApiFormRequest extends FormRequest
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->unknownKeys() as $path) {
                $validator->addFailure($path, 'UnknownField');
            }

            foreach ($this->untrimmedTooLong() as $path => $maximum) {
                if (! $validator->errors()->has($path)) {
                    $validator->addFailure($path, 'Max', [$maximum]);
                }
            }
        });
    }

    /**
     * Dotted paths of JSON body strings that are longer, as sent, than their
     * rule's `max:N`, with that maximum.
     *
     * @return array<string, int>
     */
    protected function untrimmedTooLong(): array
    {
        $raw = $this->isJson() ? json_decode($this->getContent(), true) : null;

        if (! is_array($raw)) {
            return [];
        }

        $maximums = $this->stringMaximums();
        $tooLong = [];

        foreach (Arr::dot($raw) as $path => $value) {
            $maximum = $maximums[$this->normalise((string) $path)] ?? null;

            if ($maximum !== null && is_string($value) && mb_strlen($value) > $maximum) {
                $tooLong[(string) $path] = $maximum;
            }
        }

        return $tooLong;
    }

    /**
     * The `max:N` of every rule that also declares `string`, by rule key.
     *
     * @return array<string, int>
     */
    private function stringMaximums(): array
    {
        $maximums = [];

        foreach ($this->rules() as $path => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

            if (! in_array('string', $rules, true)) {
                continue;
            }

            foreach ($rules as $rule) {
                if (is_string($rule) && str_starts_with($rule, 'max:')) {
                    $maximums[(string) $path] = (int) substr($rule, 4);
                }
            }
        }

        return $maximums;
    }

    /**
     * Dotted paths present in the body that no rule declares, in request order.
     *
     * @return list<string>
     */
    protected function unknownKeys(): array
    {
        $declared = $this->declaredPaths();
        $unknown = [];

        $this->collectUnknownKeys($this->bodyData(), '', $declared, $unknown);

        return $unknown;
    }

    /**
     * The input the request declares: the query string for GET and HEAD, the
     * JSON body otherwise. Route parameters are never subject to the rule.
     *
     * @return array<string, mixed>
     */
    protected function bodyData(): array
    {
        if ($this->isMethod('GET') || $this->isMethod('HEAD')) {
            return $this->query->all();
        }

        return $this->isJson() ? $this->json()->all() : $this->request->all();
    }

    /**
     * Rule keys plus the implicit `{field}_confirmation` of every `confirmed` field.
     *
     * @return list<string>
     */
    private function declaredPaths(): array
    {
        $paths = [];

        foreach ($this->rules() as $path => $rules) {
            $path = (string) $path;
            $paths[] = $path;

            $rules = is_string($rules) ? explode('|', $rules) : (array) $rules;

            if (in_array('confirmed', $rules, true)) {
                $paths[] = $path.'_confirmation';
            }
        }

        return $paths;
    }

    /**
     * @param  list<string>  $declared
     * @param  list<string>  $unknown
     */
    private function collectUnknownKeys(mixed $value, string $prefix, array $declared, array &$unknown): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $child) {
            $actual = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            $normalised = $this->normalise($actual);
            $isContainer = $this->isContainer($normalised, $declared);

            if (is_int($key) || in_array($normalised, $declared, true) || $isContainer) {
                if ($isContainer) {
                    $this->collectUnknownKeys($child, $actual, $declared, $unknown);
                }

                continue;
            }

            $unknown[] = $actual;
        }
    }

    /**
     * Whether any declared path lives beneath this one (`activities.*` for `activities.*.id`).
     *
     * @param  list<string>  $declared
     */
    private function isContainer(string $normalised, array $declared): bool
    {
        $prefix = $normalised.'.';

        foreach ($declared as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * `activities.0.sets.1.order` becomes `activities.*.sets.*.order`.
     */
    private function normalise(string $path): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => ctype_digit($segment) ? '*' : $segment,
            explode('.', $path),
        ));
    }
}
