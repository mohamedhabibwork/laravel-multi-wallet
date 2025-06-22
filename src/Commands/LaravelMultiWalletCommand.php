<?php

namespace HWallet\LaravelMultiWallet\Commands;

use Illuminate\Console\Command;

class LaravelMultiWalletCommand extends Command
{
    public $signature = 'laravel-multi-wallet';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
