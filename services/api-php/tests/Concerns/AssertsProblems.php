<?php

namespace Tests\Concerns;

use App\Exceptions\Api\ApiErrorResponse;
use App\Http\RequestId;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Assertions for the RFC 9457 Problem body every non-2xx API response uses.
 * Available both as `$this->assertProblem($response, …)` and, chainable, as
 * `$response->assertProblem(…)`.
 */
trait AssertsProblems
{
    protected function setUpAssertsProblems(): void
    {
        if (! TestResponse::hasMacro('assertProblem')) {
            TestResponse::macro('assertProblem', function (int $status, string $code): TestResponse {
                /** @var TestResponse $this */
                return AssertsProblems::problem($this, $status, $code);
            });
            TestResponse::macro('assertFieldErrors', function (array $fields, ?string $code = null): TestResponse {
                /** @var TestResponse $this */
                return AssertsProblems::fieldErrors($this, $fields, $code);
            });
            TestResponse::macro('fieldError', function (string $field): ?array {
                /** @var TestResponse $this */
                return AssertsProblems::firstError($this, $field);
            });
        }
    }

    protected function assertProblem(TestResponse $response, int $status, string $code): TestResponse
    {
        return self::problem($response, $status, $code);
    }

    /**
     * @param  list<string>  $fields
     */
    protected function assertFieldErrors(TestResponse $response, array $fields, ?string $code = null): TestResponse
    {
        return self::fieldErrors($response, $fields, $code);
    }

    /**
     * @return array{pointer: string, code: string, detail: string}|null
     */
    protected function fieldError(TestResponse $response, string $field): ?array
    {
        return self::firstError($response, $field);
    }

    /**
     * The response is a Problem with the given status and code: right media
     * type, the six standard members, and an `X-Request-Id` matching `instance`.
     */
    public static function problem(TestResponse $response, int $status, string $code): TestResponse
    {
        $response->assertStatus($status);

        Assert::assertStringStartsWith(
            ApiErrorResponse::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
            'Problem responses are served as application/problem+json.',
        );

        $response->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance', 'code']);
        $response->assertJsonPath('status', $status);
        $response->assertJsonPath('code', $code);
        $response->assertJsonMissingPath('message');

        $requestId = $response->headers->get(RequestId::HEADER);
        Assert::assertNotEmpty($requestId, 'Every API response carries X-Request-Id.');

        $instance = (string) $response->json('instance');
        Assert::assertStringStartsWith('/', $instance);
        Assert::assertStringEndsWith('#'.$requestId, $instance, 'The instance ends with the request id.');
        Assert::assertStringEndsWith('/problems/'.$code, (string) $response->json('type'));

        return $response;
    }

    /**
     * A 422 Problem whose `errors` cover every given field. Fields may be
     * dotted paths (`activities.0.sets`) or JSON Pointers (`/activities/0/sets`).
     *
     * @param  list<string>  $fields
     */
    public static function fieldErrors(TestResponse $response, array $fields, ?string $code = null): TestResponse
    {
        self::problem($response, 422, 'validation_failed');

        $errors = $response->json('errors');
        Assert::assertIsArray($errors);
        Assert::assertIsList($errors, '`errors` is a list of {pointer, code, detail}.');

        $byPointer = [];
        foreach ($errors as $error) {
            Assert::assertSame(['pointer', 'code', 'detail'], array_keys($error));
            $byPointer[$error['pointer']][] = $error;
        }

        foreach ($fields as $field) {
            $pointer = self::pointer($field);

            Assert::assertArrayHasKey($pointer, $byPointer, "No error for {$pointer}; got: ".implode(', ', array_keys($byPointer)));

            if ($code !== null) {
                Assert::assertSame($code, $byPointer[$pointer][0]['code'], "Unexpected code for {$pointer}.");
            }
        }

        return $response;
    }

    /**
     * @return array{pointer: string, code: string, detail: string}|null
     */
    public static function firstError(TestResponse $response, string $field): ?array
    {
        $pointer = self::pointer($field);

        foreach ((array) $response->json('errors') as $error) {
            if (($error['pointer'] ?? null) === $pointer) {
                return $error;
            }
        }

        return null;
    }

    private static function pointer(string $field): string
    {
        return str_starts_with($field, '/') ? $field : ApiErrorResponse::pointer($field);
    }
}
