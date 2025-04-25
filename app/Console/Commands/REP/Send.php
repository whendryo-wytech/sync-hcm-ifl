<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DeviceGadget;
use App\Services\Clock\DeviceHttp;
use App\Services\Clock\DeviceTemplate;
use Illuminate\Console\Command;

class Send extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:send {--clear} {--devices=} {--ips=} {--employees=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Envia os cadastros dos funcionários para o REPs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $templates = (new DeviceTemplate())->load($this->option('employees'));
        $devices = (new DeviceGadget())->load($this->option('devices'));

        foreach ($devices as $device) {
            $request = (new DeviceHttp($device));
            if ($this->option('clear')) {
                $request = $request->delete($templates->pluck('pis')->toArray());
            }
            $request->send($templates);
        }
    }
}
