<?php

namespace App\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One id per request, taken from a client-supplied `X-Request-Id` when it is a
 * UUID and generated otherwise. Memoized on the request so the log context,
 * the response header and a Problem's `instance` all agree.
 */
final class RequestId
{
    public const ATTRIBUTE = 'request_id';

    public const HEADER = 'X-Request-Id';

    public static function for(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $header = $request->header(self::HEADER);
        $id = is_string($header) && Str::isUuid($header) ? $header : (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }
}
