<?php

namespace HWallet\LaravelMultiWallet\Exceptions;

use Exception;

class InvalidBalanceTypeException extends Exception
{
    public function __construct(string $message = 'Invalid balance type provided', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
