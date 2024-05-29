<?php

namespace App\Console\Commands\Sync;

use App\Services\Sync\Sync;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class Run extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:run {--table=}';

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
        if ($this->option('table') && Str::substrCount($this->option('table'), ',') > 0) {
            foreach (explode(',', $this->option('table')) as $data) {
                $this->info($data);
                Sync::run($data);
            }
            return;
        }
        Sync::run($this->option('table'));
    }
}
