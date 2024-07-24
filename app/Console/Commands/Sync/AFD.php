<?php

namespace App\Console\Commands\Sync;

use App\Services\Sync\AFD as AFDService;
use Illuminate\Console\Command;

class AFD extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:afd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     * @throws \JsonException
     */
    public function handle()
    {
        AFDService::execute();
    }
}
