<?php

namespace App\Exceptions\Api;

use App\Enums\ErrorCode;
use App\Enums\FieldErrorCode;
use App\Http\RequestId;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Builds the RFC 9457 Problem Details body every non-2xx API response uses:
 * `{ type, title, status, detail, instance, code }`, served as
 * `application/problem+json`, plus `errors` on validation failures.
 */
class ApiErrorResponse
{
    public const CONTENT_TYPE = 'application/problem+json';

    /**
     * @var array<int, string>
     */
    private const DETAILS = [
        403 => 'This action is unauthorized.',
        404 => 'Record not found.',
        429 => 'Too many attempts.',
    ];

    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $extensions  extra members appended after the standard ones
     */
    public static function problem(int $status, ErrorCode $code, string $detail, array $headers = [], array $extensions = []): JsonResponse
    {
        $request = request();
        $requestId = RequestId::for($request);

        $body = [
            'type' => rtrim((string) config('app.url'), '/').'/problems/'.$code->value,
            'title' => $code->title(),
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getPathInfo().'#'.$requestId,
            'code' => $code->value,
        ] + $extensions;

        return response()->json($body, $status, $headers + [
            'Content-Type' => self::CONTENT_TYPE,
            RequestId::HEADER => $requestId,
        ]);
    }

    public static function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $code = $e instanceof ApiException ? $e->errorCode : ErrorCode::fromStatus($status);

        $detail = $status === 404 || $e instanceof ThrottleRequestsException || $e->getMessage() === ''
            ? (self::DETAILS[$status] ?? (Response::$statusTexts[$status] ?? 'Error'))
            : $e->getMessage();
        $detail = __($detail);

        $extensions = $code === ErrorCode::ValidationFailed ? ['errors' => []] : [];

        return self::problem($status, $code, $detail, $e->getHeaders(), $extensions);
    }

    public static function fromValidation(ValidationException $e): JsonResponse
    {
        return self::problem($e->status, ErrorCode::ValidationFailed, $e->getMessage(), [], [
            'errors' => self::validationErrors($e),
        ]);
    }

    /**
     * Unhandled exceptions: a generic 500 Problem; the exception itself is
     * still reported by the handler.
     */
    public static function fromThrowable(Throwable $e): JsonResponse
    {
        $detail = config('app.debug') ? $e->getMessage() : '';

        return self::problem(500, ErrorCode::InternalError, $detail !== '' ? $detail : __('Server Error'));
    }

    /**
     * One entry per validation message: the field as a JSON Pointer, the
     * failed rule mapped to a `FieldErrorCode`, and the localized message.
     *
     * @return list<array{pointer: string, code: string, detail: string}>
     */
    public static function validationErrors(ValidationException $e): array
    {
        $failed = $e->validator->failed();
        $declared = $e->validator->getRules();
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            $rules = array_map(
                fn (string $rule): string => Str::snake(class_basename($rule)),
                array_keys($failed[$field] ?? []),
            );
            $declaredRules = $declared[$field] ?? $declared[self::wildcardPath($field)] ?? [];

            foreach (array_values($messages) as $index => $message) {
                $rule = match (true) {
                    count($rules) === count($messages) => $rules[$index],
                    count($rules) === 1 => $rules[0],
                    default => null,
                };

                $errors[] = [
                    'pointer' => self::pointer($field),
                    'code' => ($rule === null ? FieldErrorCode::InvalidValue : FieldErrorCode::fromRule($rule, $declaredRules))->value,
                    'detail' => $message,
                ];
            }
        }

        return $errors;
    }

    /**
     * `activities.0.sets.1.effort_value` becomes `/activities/0/sets/1/effort_value`
     * (RFC 6901: `~` is `~0`, `/` is `~1` inside a segment).
     */
    public static function pointer(string $dottedPath): string
    {
        $segments = array_map(
            static fn (string $segment): string => str_replace(['~', '/'], ['~0', '~1'], $segment),
            explode('.', $dottedPath),
        );

        return '/'.implode('/', $segments);
    }

    /**
     * `activities.0.sets.1.order` becomes `activities.*.sets.*.order`, the key
     * the rules were declared under.
     */
    private static function wildcardPath(string $field): string
    {
        return implode('.', array_map(
            static fn (string $segment): string => ctype_digit($segment) ? '*' : $segment,
            explode('.', $field),
        ));
    }
}
