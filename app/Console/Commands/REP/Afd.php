<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DeviceAfd;
use App\Services\Clock\DeviceGadget;
use Illuminate\Console\Command;

class Afd extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:afd';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Efetua a leitura dos REPs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $devices = (new DeviceGadget())->getMaster();

        (new DeviceAfd($devices))->load('2025-04-26');

        dd(0);

        foreach ($devices as $device) {
            (new DeviceAfd($device))->load();
        }
    }
}
