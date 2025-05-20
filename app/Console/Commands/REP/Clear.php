<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DeviceGadget;
use App\Services\Clock\DeviceHttp;
use Illuminate\Console\Command;

class Clear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:clear {--devices=} {--ips=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Remove os templates dos REPs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $devices = (new DeviceGadget())->getDevices($this->option('devices'));

        foreach ($devices as $device) {
            (new DeviceHttp($device))->clear();
        }
    }
}
