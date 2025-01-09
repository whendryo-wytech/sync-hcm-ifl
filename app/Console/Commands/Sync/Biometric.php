<?php

namespace App\Console\Commands\Sync;

use App\Services\Sync\BiometricTemplate;
use Illuminate\Console\Command;

class Biometric extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:biometric {--no-delete}{--reload}';

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
        if ($this->option('reload')) {
            BiometricTemplate::reload();
            return;
        }
        BiometricTemplate::handle(!$this->option('no-delete'));
    }
}
