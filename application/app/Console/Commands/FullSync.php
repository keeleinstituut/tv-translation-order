<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FullSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:all';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Artisan::call('sync:classifier-values');
        Artisan::call('sync:institutions');
        Artisan::call('sync:institution-users');
    }
}
