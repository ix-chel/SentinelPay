<?php

namespace App\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct(string $message = 'Account not found.', int $code = 404)
    {
        parent::__construct($message, $code);
    }
}
