<?php

namespace App\Exceptions\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Builds the `{ message, code }` envelope every non-2xx API response uses.
 */
class ApiErrorResponse
{
    /**
     * @var array<int, string>
     */
    private const CODES = [
        400 => 'bad_request',
        401 => 'unauthenticated',
        403 => 'forbidden',
        404 => 'not_found',
        405 => 'method_not_allowed',
        409 => 'conflict',
        419 => 'csrf_token_mismatch',
        422 => 'validation_failed',
        429 => 'too_many_requests',
    ];

    /**
     * @var array<int, string>
     */
    private const MESSAGES = [
        403 => 'This action is unauthorized.',
        404 => 'Record not found.',
        429 => 'Too many attempts.',
    ];

    public static function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        $code = $e instanceof ApiException
            ? $e->errorCode
            : (self::CODES[$status] ?? 'http_'.$status);

        $message = $status === 404 || $e->getMessage() === ''
            ? (self::MESSAGES[$status] ?? (Response::$statusTexts[$status] ?? 'Error'))
            : $e->getMessage();

        return response()->json(['message' => $message, 'code' => $code], $status, $e->getHeaders());
    }

    public static function fromValidation(ValidationException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => 'validation_failed',
            'errors' => self::validationErrors($e),
        ], $e->status);
    }

    /**
     * Pair every message with the rule that produced it, e.g. `required`,
     * `email`, `unique`, `enum`, `heart_rate_zone_within_range`. Messages
     * added manually (no rule) get `invalid`.
     *
     * @return array<string, list<array{code: string, message: string}>>
     */
    public static function validationErrors(ValidationException $e): array
    {
        $failed = $e->validator->failed();
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $rules = array_map(
                fn (string $rule): string => Str::snake(class_basename($rule)),
                array_keys($failed[$field] ?? []),
            );

            foreach (array_values($messages) as $index => $message) {
                $code = match (true) {
                    count($rules) === count($messages) => $rules[$index],
                    count($rules) === 1 => $rules[0],
                    default => 'invalid',
                };

                $errors[$field][] = ['code' => $code, 'message' => $message];
            }
        }

        return $errors;
    }
}
