<?php

namespace App\Console\Commands\Sandbox;

use App\Services\Clock\Devices;
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
     */
    public function handle(): void
    {
        //Devices::getToken(Devices::getMasterDevice())
        dd(Devices::getMasterDevice());
    }
}
