<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a request reuses an existing idempotency key
 * with a different request payload.
 */
class IdempotencyConflictException extends RuntimeException
{
    public function __construct(
        string $message = 'The idempotency key is already associated with a different request.',
        int $code = 409,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
