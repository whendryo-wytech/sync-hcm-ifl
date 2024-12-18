<?php

namespace App\Services\Sync;

use App\Models\SeniorOld;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BiometricTemplate
{

    private static string $urlLogin = '/login.fcgi';
    private static string $urlDelete = '/remove_users.fcgi?session=';
    private static string $urlCreate = '/add_users.fcgi?session=';

    /**
     * @throws \JsonException
     */
    public static function handle(bool $deletePendencies): void
    {
        $employees = [];
        foreach (
            (new SeniorOld())->setTable('RTC_PENDENCIES')
                ->where('TABLENAME', 'R070BIO')
                ->get() as $item
        ) {
            $keys = (array)json_decode(Str::lower($item->recordkey), false, 512, JSON_THROW_ON_ERROR);
            $employee = DB::connection('senior_old')->select(
                "SELECT A.NUMEMP
                              ,A.TIPCOL
                              ,A.NUMCAD
                              ,B.NOMFUN
                              ,B.NUMPIS
                              ,B.NUMCPF
                              ,A.NUMFIS
                              ,A.NUMCRA
                              ,A.TIPCRA
                        FROM R070CON A
                        LEFT JOIN R034FUN B ON
                              B.NUMEMP = A.NUMEMP
                          AND B.TIPCOL = A.TIPCOL
                          AND B.NUMCAD = A.NUMCAD
                        WHERE 1=1
                          AND A.TIPCRA = 1
                          AND a.IDTPES = '{$keys['idtpes']}'"
            );

            $templates = (new SeniorOld())->setTable('R070BIO')
                ->where('idtpes', $keys['idtpes'])
                ->where('codtbi', $keys['codtbi']);

            $employees[$item->operationkind][$keys['idtpes']] = [
                'name'           => $employee[0]->nomfun,
                'pis'            => (int)$employee[0]->numpis,
                'code'           => 0,
                'template_count' => $templates->count(),
                'templates'      => $templates->select()->get()->pluck('tembio')->toArray(),
                'password'       => "",
                'admin'          => false,
                'rfid'           => (int)$employee[0]->numfis,
                'bars'           => "",
                "registraion"    => 0
            ];
        }

        try {
            Log::channel('biometric')->info("Iniciando Processo de Biometria");
            Log::channel('biometric')->info(
                "Processo da Biometria: Apagar PENDENCIES? ".($deletePendencies ? 'Sim' : 'Não')
            );
            Log::channel('biometric')->info(
                "------------------------------------------------------------------------"
            );

            if (!$employees) {
                Log::channel('biometric')->info("Requisição Processo: Não há informações para serem integradas");
            }

            if ($employees) {
                foreach (static::getDevices() as $device) {
                    $deviceName = $device->codrlg.' - '.$device->desrlg;
                    Log::channel('biometric')->info("Iniciando Processo da Biometria $deviceName");
                    Log::channel('biometric')->info("IP: $device->numeip");

                    $token = static::getToken($device);
                    if (!$token) {
                        Log::channel('biometric')->info("Processo da Biometria: Não foi possível obter o token");
                    }
                    if ($token) {
                        static::delete($device, $token, collect([...$employees['D'] ?? [], ...$employees['U'] ?? []]));
                        static::create($device, $token, collect([...$employees['I'] ?? [], ...$employees['U'] ?? []]));
                    }
                    Log::channel('biometric')->info("Finalizando Processo da Biometria $deviceName");
                    Log::channel('biometric')->info(
                        "------------------------------------------------------------------------"
                    );
                }
                if ($deletePendencies) {
                    (new SeniorOld())->setTable('RTC_PENDENCIES')->where('TABLENAME', 'R070BIO')->delete();
                }
            }
        } catch (Exception $e) {
            Log::channel('biometric')->info("Processo de Biometria: Erro ".$e->getMessage());
        }

        Log::channel('biometric')->info("Finalizando Processo de Biometria");
        Log::channel('biometric')->info("------------------------------------------------------------------------");
    }

    private static function getDevices(): array
    {
        return DB::connection('senior_old')->select(
            "   select codrlg
                      ,desrlg
                      ,regexp_replace(numeip, '0*([0-9]+)', '\\1') AS numeip
                from r058rlg a
                where 1=1
                  and modrlg = 350
                order by codrlg "
        );
    }

    private static function getToken(object $device): ?string
    {
        $http = Http::withoutVerifying();

        try {
            $url = "https://$device->numeip".static::$urlLogin;
            $body = [
                'login'    => env('CONTROLID_USER', 'admin'),
                'password' => env('CONTROLID_PSWD', 'admin')
            ];

            $bodyText = collect([
                'login'    => '*****',
                'password' => '*****',
            ]);
            $bodyText = $bodyText->toJson(JSON_THROW_ON_ERROR);

            $response = $http->post($url, $body);

            Log::channel('biometric')->info("Requisição de login: URL $url ");
            Log::channel('biometric')->info("Requisição de login: BODY $bodyText");
            Log::channel('biometric')->info("Requisição de login: HTTP {$response->status()}");

            if (!$response->ok()) {
                Log::channel('biometric')->info("Requisição de login: {$response->body()}");
            }

            if ($response->ok()) {
                Log::channel('biometric')->info("Requisição de login: OK");
                return $response->json('session');
            }
        } catch (ConnectionException $e) {
            Log::channel('biometric')->info("Requisição de login: Erro ".$e->getMessage());
        }

        return null;
    }

    /**
     * @throws \JsonException
     */
    private static function create(object $device, string $token, Collection $employees): void
    {
        $http = Http::withoutVerifying();

        if ($employees->isEmpty()) {
            Log::channel('biometric')->info("Requisição Inclusão: Não há informações para serem integradas");
        }

        if (!$employees->isEmpty()) {
            try {
                $url = "https://$device->numeip".static::$urlCreate.$token;
                $body = [
                    "users" => $employees->values()->toArray()
                ];

                $bodyText = collect($body['users']);
                $bodyText->transform(function ($i) {
                    unset($i['templates']);
                    return $i;
                });
                $bodyText = $bodyText->toJson(JSON_THROW_ON_ERROR);

                $response = $http->post($url, $body);

                Log::channel('biometric')->info("Requisição de Inclusão: URL $url ");
                Log::channel('biometric')->info("Requisição Inclusão: BODY $bodyText");
                Log::channel('biometric')->info("Requisição Inclusão: HTTP {$response->status()}");

                if (!$response->ok()) {
                    Log::channel('biometric')->info("Requisição Inclusão: {$response->body()}");
                }

                if ($response->ok()) {
                    Log::channel('biometric')->info("Requisição Inclusão: OK");
                }
            } catch (ConnectionException $e) {
                Log::channel('biometric')->info("Requisição Inclusão: Erro ".$e->getMessage());
            }
        }
    }

    /**
     * @throws \JsonException
     */
    private static function delete(object $device, string $token, Collection $employees): void
    {
        $http = Http::withoutVerifying();

        if ($employees->isEmpty()) {
            Log::channel('biometric')->info("Requisição Exclusão: Não há informações para serem integradas");
        }

        if (!$employees->isEmpty()) {
            try {
                $url = "https://$device->numeip".static::$urlDelete.$token;
                $body = [
                    "users" => $employees->pluck('pis')->toArray()
                ];
                $response = $http->post($url, $body);
                Log::channel('biometric')->info("Requisição de Exclusão: URL $url ");
                Log::channel('biometric')->info(
                    "Requisição Exclusão: BODY ".json_encode($body, JSON_THROW_ON_ERROR)
                );
                Log::channel('biometric')->info("Requisição Exclusão: HTTP {$response->status()}");

                if (!$response->ok()) {
                    Log::channel('biometric')->info("Requisição Exclusão: {$response->body()}");
                }

                if ($response->ok()) {
                    Log::channel('biometric')->info("Requisição Exclusão: OK");
                }
            } catch (ConnectionException $e) {
                Log::channel('biometric')->info("Requisição Exclusão: Erro ".$e->getMessage());
            }
        }
    }


}
