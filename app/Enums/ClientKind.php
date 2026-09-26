<?php

namespace App\Enums;

/**
 * How a client receives its refresh token: web clients get an httpOnly cookie,
 * native clients get it in the response body and keep it in secure storage.
 */
enum ClientKind: string
{
    case Web = 'web';
    case Native = 'native';
}
