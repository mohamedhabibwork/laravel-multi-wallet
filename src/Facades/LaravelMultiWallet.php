<?php

namespace HWallet\LaravelMultiWallet\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \HWallet\LaravelMultiWallet\LaravelMultiWallet
 */
class LaravelMultiWallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \HWallet\LaravelMultiWallet\LaravelMultiWallet::class;
    }
}
