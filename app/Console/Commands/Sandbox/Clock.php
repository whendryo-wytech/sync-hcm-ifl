<?php

namespace App\Console\Commands\Sandbox;

use App\Services\Clock\DevicesOld;
use Illuminate\Console\Command;

class Clock extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sandbox:clock';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sandbox - Clock';

    /**
     * Execute the console command.
     * @throws \JsonException
     */
    public function handle(): void
    {
//        $device = Devices::getMasterDevice();
//        Devices::deleteUsers($device);
//        Devices::addUsers($device, reload: false);


        $device = DevicesOld::getDeviceByIp('172.20.2.102');
        DevicesOld::deleteUsers($device);
//        Devices::addUsers($device, originLoad: Devices::ORIGIN_MASTER, async: false);
    }
}
