<?php

namespace App\Exceptions;

use RuntimeException;

/** Contains only a safe user-facing message; provider diagnostics stay in sanitized logs. */
class PasswordResetOtpException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $throttled = false)
    {
        parent::__construct($message);
    }
}
