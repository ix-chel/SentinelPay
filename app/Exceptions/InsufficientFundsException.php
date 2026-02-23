<?php

namespace App\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(string $message = 'Insufficient funds.', int $code = 422)
    {
        parent::__construct($message, $code);
    }
}
