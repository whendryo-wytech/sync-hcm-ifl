<?php

namespace App\Services\Clock;

use App\Models\Main\Device;
use App\Models\Senior\R058RLG;
use App\Models\Senior\SeniorOld;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Devices
{
    private static string $urlLogin = '/login.fcgi';
    private static string $urlDelete = '/remove_users.fcgi?session=';
    private static string $urlCreate = '/add_users.fcgi?session=';
    private static string $urlExport = '/export_users_csv.fcgi?session=';

    private static function setDevices(Collection $devices): void
    {
        foreach ($devices as $device) {
            Device::updateOrCreate([
                'hcm_id' => $device->codrlg
            ], [
                'hcm_id' => $device->codrlg,
                'name'   => $device->desrlg,
                'ip'     => $device->numeip,
            ]);
        }
    }

    private static function getSeniorDevices(string $where = null): Collection
    {
        dd(R058RLG::where('codrlg', 26)->get());
        $devices = (new SeniorOld())->setTable('R058RLG');
        if ($where) {
            $devices = $devices->whereRaw($where);
        }
        dd($devices);
        $devices = $devices->select(
            'codrlg',
            'desrlg',
            DB::raw('regexp_replace(numeip, \'0*([0-9]+)\', \'\\1\') AS numeip')
        );
        return $devices->get();
    }

    public static function getMasterDevice(): Device
    {
        static::setDevices(static::getSeniorDevices("codrlg = ".env('DEVICE_MASTER_REP')));
        return Device::where('hcm_id', env('DEVICE_MASTER_REP'))->first();
    }

    public static function getDevices(): Collection
    {
        static::setDevices(static::getSeniorDevices());
        return Device::all();
    }

    public static function getToken(Device $device): ?string
    {
        $http = Http::withoutVerifying();

        try {
            $url = "https://$device->ip".static::$urlLogin;
            $body = [
                'login'    => env('DEVICE_USER_REP', 'admin'),
                'password' => env('DEVICE_USER_PASSWORD', 'admin')
            ];

            $response = $http->post($url, $body);

            if (!$response->ok()) {
                Log::channel('biometric')->info("Requisição de login: {$response->body()}");
            }

            if ($response->ok()) {
                $device->update(['token' => $response->json('session')]);
                return $response->json('session');
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return null;
    }


}
