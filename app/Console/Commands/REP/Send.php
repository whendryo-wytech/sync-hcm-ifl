<?php

namespace App\Console\Commands\REP;

use App\Models\Main\Device;
use App\Services\Clock\DeviceGadget;
use App\Services\Clock\DeviceHttp;
use App\Services\Clock\DeviceTemplate;
use Illuminate\Console\Command;
use PHPUnit\Event\RuntimeException;

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
        $devices = $this->option('devices') ?? implode(
            ',',
            Device::where('hcm_id', '<>', env('DEVICE_MASTER_REP'))->pluck(
                'hcm_id'
            )->toArray()
        );
        $devices = (new DeviceGadget())->load($devices);

        if ($devices->count() > 1) {
            foreach ($devices as $device) {
                if ((int)$device->hcm_id === (int)env('DEVICE_MASTER_REP')) {
                    throw new RuntimeException("Não é possível enviar para o REP master. Selecione somente o Master.");
                }
            }
        }

        $templates = [];
        if (($devices->count() === 1) && ((int)$devices[0]->hcm_id === (int)env('DEVICE_MASTER_REP'))) {
            $templates = (new DeviceTemplate())->load($this->option('employees'));
        }
        $templates = empty($templates) ? (new DeviceTemplate())->getTemplates($this->option('employees')) : $templates;

        dd($templates);

        foreach ($devices as $device) {
            $this->info("REP ".$device->hcm_id." Templates: ".count($templates));
            $request = (new DeviceHttp($device));
            if ($this->option('clear')) {
                $request = $request->delete($templates->pluck('pis')->toArray());
            }
            $request->sendChunk($templates, 100);
        }
    }
}
