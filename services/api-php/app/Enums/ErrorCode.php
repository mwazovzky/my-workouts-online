<?php

namespace App\Enums;

/**
 * Every `code` an API Problem can carry. Mirrors the `ErrorCode` enum in
 * `contracts/openapi.yaml`; a backend may not emit a code outside this list.
 */
enum ErrorCode: string
{
    case BadRequest = 'bad_request';
    case Unauthenticated = 'unauthenticated';
    case TokenExpired = 'token_expired';
    case RefreshTokenMissing = 'refresh_token_missing';
    case RefreshTokenInvalid = 'refresh_token_invalid';
    case RefreshTokenExpired = 'refresh_token_expired';
    case RefreshTokenReused = 'refresh_token_reused';
    case Forbidden = 'forbidden';
    case EmailUnverified = 'email_unverified';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case Conflict = 'conflict';
    case InUse = 'in_use';
    case ValidationFailed = 'validation_failed';
    case TooManyRequests = 'too_many_requests';
    case InvalidVerificationLink = 'invalid_verification_link';
    case InvalidSignature = 'invalid_signature';
    case InternalError = 'internal_error';

    /**
     * The stable, English Problem `title`; `detail` carries the localized text.
     */
    public function title(): string
    {
        return match ($this) {
            self::BadRequest => 'Bad request',
            self::Unauthenticated => 'Unauthenticated',
            self::TokenExpired => 'Access token expired',
            self::RefreshTokenMissing => 'Refresh token missing',
            self::RefreshTokenInvalid => 'Refresh token invalid',
            self::RefreshTokenExpired => 'Refresh token expired',
            self::RefreshTokenReused => 'Refresh token reuse detected',
            self::Forbidden => 'Forbidden',
            self::EmailUnverified => 'Email address not verified',
            self::NotFound => 'Not found',
            self::MethodNotAllowed => 'Method not allowed',
            self::Conflict => 'Conflict',
            self::InUse => 'Resource in use',
            self::ValidationFailed => 'Validation failed',
            self::TooManyRequests => 'Too many requests',
            self::InvalidVerificationLink => 'Invalid verification link',
            self::InvalidSignature => 'Invalid signature',
            self::InternalError => 'Internal server error',
        };
    }

    /**
     * The default code for an HTTP status that carries no explicit one.
     */
    public static function fromStatus(int $status): self
    {
        return match (true) {
            $status === 400 => self::BadRequest,
            $status === 401 => self::Unauthenticated,
            $status === 403 => self::Forbidden,
            $status === 404 => self::NotFound,
            $status === 405 => self::MethodNotAllowed,
            $status === 409 => self::Conflict,
            $status === 422 => self::ValidationFailed,
            $status === 429 => self::TooManyRequests,
            $status >= 500 => self::InternalError,
            default => self::BadRequest,
        };
    }
}
