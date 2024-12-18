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
    protected $signature = 'sync:biometric {--no-delete}';

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
        BiometricTemplate::handle(!$this->option('no-delete'));
    }
}
