<?php

namespace App\Services\Clock;

use App\Models\Main\Device;
use App\Models\Main\DeviceUser;
use App\Models\Senior\R058RLG;
use App\Models\Senior\R070BIO;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Devices
{
    private static string $urlLogin = '/login.fcgi';
    private static string $urlSessionValidation = '/session_is_valid.fcgi?session=';
    private static string $urlRemoveUsers = '/remove_users.fcgi?session=';
    private static string $urlAddUsers = '/add_users.fcgi?session=';
    private static string $urlExport = '/export_users_csv.fcgi?session=';
    private static string $urlLoadUsers = '/load_users.fcgi?session=';

    private static function getSeniorDevices(string $where = null): Collection
    {
        $devices = (new R058RLG('senior_old'));
        if ($where) {
            $devices = $devices->whereRaw($where);
        }
        $devices = $devices->where('modrlg', 350);
        $devices = $devices->select(
            'codrlg',
            'desrlg',
            DB::raw('regexp_replace(numeip, \'0*([0-9]+)\', \'\\1\') AS numeip')
        );
        return $devices->get();
    }

    /**
     * @throws \JsonException
     */
    public static function getSeniorUsers(?Device $device, array $employees = []): ?array
    {
        if (!$device) {
            return null;
        }

        $sql = implode(",", $employees);
        if ($sql !== "") {
            $sql = " AND B.NUMCAD IN ($sql) ";
        }
        if ($sql === "") {
            $sql = " AND 1=1 ";
        }

        $users = DB::connection('senior_old')->select(
            "SELECT A.NUMEMP
                          ,A.TIPCOL
                          ,A.NUMCAD
                          ,B.NOMFUN
                          ,B.NUMPIS
                          ,B.NUMCPF
                          ,A.NUMFIS
                          ,A.NUMCRA
                          ,A.TIPCRA
                          ,A.IDTPES
                    FROM R070CON A
                    LEFT JOIN R034FUN B ON
                          B.NUMEMP = A.NUMEMP
                      AND B.TIPCOL = A.TIPCOL
                      AND B.NUMCAD = A.NUMCAD
                    WHERE 1=1 $sql
                      AND A.NUMEMP = 1
                      AND A.TIPCRA = 1
                      AND B.TIPCOL = 1
                      AND B.TIPCON IN (1,10)
                      AND B.SITAFA NOT IN (7)
                      AND B.CONRHO IN (1,2)
                    ORDER BY B.NUMCAD"
        );

        $usersCreate = [];
        foreach ($users as $user) {
            $templates = (new R070BIO('senior_old'))->where('idtpes', $user->idtpes)->where('codtbi', 2);

            $data = [
                'name'            => $user->nomfun,
                'pis'             => (int)$user->numpis,
                'code'            => 0,
                'templates_count' => $templates->count(),
                'templates'       => $templates->select()->get()->pluck('tembio')->toArray(),
                'password'        => "",
                'admin'           => in_array($user->numcad, explode(',', env('DEVICE_USERS_ADMIN', '')), true),
                'rfid'            => (int)$user->numfis,
                'bars'            => "",
                "registration"    => 0
            ];

            $usersCreate[] = DeviceUser::updateOrCreate([
                'hcm_id' => $device->hcm_id,
                'pis'    => $data['pis']
            ], [
                'hcm_id'   => $device->hcm_id,
                'name'     => $data['name'],
                'pis'      => $data['pis'],
                'cpf'      => $user->numcpf,
                'rfid'     => $data['rfid'],
                'request'  => json_encode($data, JSON_THROW_ON_ERROR),
                'response' => null
            ]);
        }
        return $usersCreate;
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

    public static function getToken(Device $device, int $tries = 0): ?Device
    {
        if (static::getSessionValidation($device)) {
            return $device;
        }

        $http = Http::withoutVerifying();

        try {
            $url = "https://$device->ip".static::$urlLogin;
            $body = [
                'login'    => env('DEVICE_USER_REP', 'admin'),
                'password' => env('DEVICE_USER_PASSWORD', 'admin')
            ];

            $response = $http->post($url, $body);

            if (!$response->ok()) {
                Log::channel('biometric')->info("[ERRO] Requisição de login: {$response->body()}");
                if ($tries <= 3) {
                    sleep(1);
                    return static::getToken($device, $tries + 1);
                }
                return null;
            }

            if ($response->ok()) {
                $device->update(['token' => $response->json('session')]);
                return $device;
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return null;
    }

    public static function getSessionValidation(?Device $device, int $tries = 0): bool
    {
        if (!$device) {
            return false;
        }

        $http = Http::withoutVerifying();

        try {
            $url = "https://$device->ip".static::$urlSessionValidation.$device->token;

            $response = $http->post($url, null);

            if ($response->ok()) {
                if ($response->json('session_is_valid')) {
                    return true;
                }
            }

            if ($tries <= 3) {
                sleep(1);
                return static::getSessionValidation(static::getToken($device), $tries + 1);
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return false;
    }

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

    private static function setLoadDeviceUsers(?Device $device, int $offset): bool
    {
        if (!$device) {
            return false;
        }

        if ($offset === 0) {
            DeviceUser::where('hcm_id', $device->hcm_id)->delete();
        }

        $http = Http::withoutVerifying();

        try {
            if (!static::getSessionValidation($device)) {
                return false;
            }

            $device->refresh();

            $url = "https://$device->ip".static::$urlLoadUsers.$device->token;

            $response = $http->post($url, [
                'limit'     => 100,
                'offset'    => $offset,
                'templates' => false,
            ]);

            if (!$response->ok()) {
                return false;
            }

            if ($response->ok()) {
                if (count($response->json('users')) === 0) {
                    return false;
                }
                foreach ($response->json('users') as $user) {
                    DeviceUser::create([
                        'hcm_id' => $device->hcm_id,
                        'name'   => $user['name'] ?? null,
                        'pis'    => $user['pis'] ?? null,
                        'cpf'    => $user['cpf'] ?? null,
                        'rfid'   => $user['rfid'] ?? null,
                    ]);
                }
                return true;
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return false;
    }

    public static function setLoadUsers(Device $device): void
    {
        $offset = 0;
        while (static::setLoadDeviceUsers($device, $offset)) {
            $offset += 100;
        }
    }


    public static function addUser(DeviceUser $user, int $tries = 0): bool
    {
        $http = Http::withoutVerifying();

        try {
            if (!static::getSessionValidation($user->device)) {
                return false;
            }

            $user->device->refresh();

            $url = "https://".$user->device->ip.static::$urlAddUsers.$user->device->token;

            $response = $http->post($url, [
                'users' => [json_decode($user->request, true, 512, JSON_THROW_ON_ERROR)]
            ]);

            if (!$response->ok()) {
                if (Str::contains(Str::upper(Str::ascii($response->json('error'))), 'JA CADASTRADO')) {
                    $user->update(['response' => 'OK']);
                    return true;
                }

                $user->update(['response' => $response->body()]);

                if ($tries <= 3) {
                    sleep(1);
                    return static::addUser($user, $tries + 1);
                }

                return false;
            }

            if ($response->ok()) {
                $user->update(['response' => 'OK']);
                return true;
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }
        return false;
    }

    /**
     * @throws \JsonException
     */
    public static function addUsers(?Device $device, array $employees = []): void
    {
        if (!$device) {
            return;
        }
        $users = static::getSeniorUsers($device, $employees);
        foreach ($users as $user) {
            dump($user);
//            static::addUser($user);
        }
    }

    public static function deleteUsers(?Device $device, int $limit = 9999999): bool
    {
        if (!$device) {
            return false;
        }

        static::setLoadUsers($device);

        $http = Http::withoutVerifying();

        try {
            if (!static::getSessionValidation($device)) {
                return false;
            }

            $device->refresh();

            $url = "https://$device->ip".static::$urlRemoveUsers.$device->token;

            $users = DeviceUser::where('hcm_id', $device->hcm_id)
                ->select('pis')
                ->limit($limit)
                ->get()
                ->pluck('pis')
                ->toArray();

            if (count($users) === 0) {
                return false;
            }

            $response = $http->post($url, [
                'users' => array_map('intval', $users)
            ]);

            if (!$response->ok()) {
                return false;
            }

            if ($response->ok()) {
                DeviceUser::where('hcm_id', $device->hcm_id)
                    ->whereIn('pis', $users)
                    ->delete();
                return true;
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return false;
    }


}
