<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DeviceTemplate;
use Illuminate\Console\Command;

class Template extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:master';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Sincroniza os templates com o REP master';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dd((new DeviceTemplate())->loadByMaster());
    }
}
