<?php

namespace App\Console\Commands\Sync;

use App\Services\Sync\Sync;
use Illuminate\Console\Command;

class Sandbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:sandbox {--table=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        dd(Sync::run($this->option('table')));
    }
}
