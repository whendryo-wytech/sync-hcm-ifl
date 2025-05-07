<?php

namespace App\Console\Commands\REP;

use App\Services\Clock\DevicePendency;
use Illuminate\Console\Command;
use Throwable;

class Pendency extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rep:pendency';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'REPs - Sincroniza os templates com os REPs com base nas pendências';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle(): void
    {
        $pendencies = (new DevicePendency())->getPendencies();

        dd($pendencies);
        /*
        Log::channel('rep')->info("Iniciando Sincronia de Pendências");
        try {
            DB::transaction(function () {
                $pendencies = (new DevicePendency())->getPendencies();

                Log::channel('rep')->info("Quantidade: ".count($pendencies->get('pendencies')));
                $ids = implode(',', array_values(array_map(static function ($item) {
                    return (int)$item->numcad;
                }, $pendencies->get('employees'))));
                Log::channel('rep')->info("Cadastros: $ids");

                if (count($pendencies->get('pendencies')) > 0) {
                    foreach ((new DeviceGadget())->getDevicesWithoutMaster() as $device) {
                        $start = Carbon::now();

                        $templates = $pendencies->get('templates');

                        $pis = array_values(array_map(static function ($item) {
                            return (int)$item->numpis;
                        }, $pendencies->get('employees')));

                        $this->info("REP ".$device->hcm_id." Templates: ".count($templates));

                        Log::channel('rep')->info("REP: $device->hcm_id - $device->name - $device->ip");

                        (new DeviceHttp($device))->delete($pis)->sendChunk($templates, 100);

                        Log::channel('rep')->info(
                            "Tempo de execução: ".($start->diff(Carbon::now()))->forHumans(['parts' => 3])
                        );
                        Log::channel('rep')->info("******************************");
                    }

                    Log::channel('rep')->info("Limpando pendências");
                    foreach ($pendencies->get('pendencies') as $key => $pendency) {
                        Log::channel('rep')->info("Limpando pendência: $key");
                        (new SeniorOld())->setTable('RTC_PENDENCIES')->where('ID', $key)->delete();
                    }
                }
            });
        } catch (Throwable $e) {
            Log::channel('rep')->info("Erro: ".$e->getMessage());
        }
        Log::channel('rep')->info("Finalizando Sincronia de Pendências");
        Log::channel('rep')->info("-----------------------------------------------");
        */
    }
}
