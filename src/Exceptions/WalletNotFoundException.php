<?php

namespace HWallet\LaravelMultiWallet\Exceptions;

use Exception;

class WalletNotFoundException extends Exception
{
    public function __construct(string $message = 'Wallet not found', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
