<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DeviceGadget;
use App\Services\Clock\DeviceTemplate;
use Illuminate\Console\Command;

class Reload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:reload';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Recarrega os dados dos REPs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        (new DeviceGadget())->load();
        (new DeviceTemplate())->load();
    }
}
