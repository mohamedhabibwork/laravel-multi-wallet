<?php

namespace HWallet\LaravelMultiWallet\Exceptions;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct(string $message = 'Insufficient funds for this operation', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
