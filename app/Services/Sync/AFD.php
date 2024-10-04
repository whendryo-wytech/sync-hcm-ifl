<?php

namespace App\Services\Sync;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AFD
{

    private static string $urlLogin = '/login.fcgi';
    private static string $urlAfd = '/get_afd.fcgi?session=';

    public static function execute(): void
    {
        $http = Http::withoutVerifying();

        $devices = DB::connection('senior_old')->select(
            "   select codrlg
                      ,desrlg
                      ,regexp_replace(numeip, '0*([0-9]+)', '\\1') AS numeip
                from r058rlg a
                where 1=1
                  and modrlg = 350
                order by codrlg "
        );

        foreach ($devices as $device) {
            $deviceName = $device->codrlg.' - '.$device->desrlg;
            Log::channel('afd')->info("Iniciando integração do REP $deviceName");
            Log::channel('afd')->info("IP: $device->numeip");

            try {
                $url = "https://$device->numeip".static::$urlLogin;
                $body = [
                    'login'    => env('CONTROLID_USER', 'admin'),
                    'password' => env('CONTROLID_PSWD', 'admin')
                ];
                $response = $http->post($url, $body);

                Log::channel('afd')->info("Requisição de login: URL $url ");
                Log::channel('afd')->info("Requisição de login: BODY ".json_encode($body));
                Log::channel('afd')->info("Requisição de login: HTTP {$response->status()}");

                if (!$response->ok()) {
                    Log::channel('afd')->info("Requisição de login: {$response->body()}");
                }

                if ($response->ok()) {
                    Log::channel('afd')->info("Requisição de login: OK");

                    $date = Carbon::now()->subMonth();

                    $url = "https://$device->numeip".static::$urlAfd.$response->json('session');
                    $body = [
                        "initial_date" => [
                            "day"   => (int)$date->format('d'),
                            "month" => (int)$date->format('m'),
                            "year"  => (int)$date->format('Y')
                        ]
                    ];
                    $response = $http->post($url, $body);

                    Log::channel('afd')->info("Requisição de login: URL $url ");
                    Log::channel('afd')->info("Requisição de AFD: BODY ".json_encode($body));
                    Log::channel('afd')->info("Requisição de AFD: HTTP {$response->status()}");

                    if (!$response->ok()) {
                        Log::channel('afd')->info("Requisição de AFD: {$response->body()}");
                    }

                    if ($response->ok()) {
                        Log::channel('afd')->info("Requisição de AFD: OK");
                        $fileName = "$deviceName - ".$date->format('d-m-Y').' a '.Carbon::now()->format('d-m-Y').'.txt';
                        $fileName = Str::replace(['/', '\\'], "-", $fileName);
                        Storage::disk('afd')->put($fileName, $response->body());
                        Log::channel('afd')->info("Requisição de AFD salvo: ".Storage::disk('afd')->path($fileName));
                    }
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::channel('afd')->info("Erro: ".$e->getMessage());
            }

            Log::channel('afd')->info("Finalizando integração do REP $deviceName");
            Log::channel('afd')->info("------------------------------------------------------------------------");
        }
    }

}
