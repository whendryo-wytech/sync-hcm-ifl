<?php

namespace App\Services\Clock;

use App\Models\Main\Device as DeviceModel;
use App\Models\Main\DeviceTemplate;
use App\Models\Main\Template;
use App\Models\Senior\R058RLG;
use App\Models\Senior\R070BIO;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DeviceOld2
{
    private const string URL_LOGIN = '/login.fcgi';
    private const string URL_SESSION_VALIDATION = '/session_is_valid.fcgi?session=';
    private const string URL_REMOVE_USERS = '/remove_users.fcgi?session=';
    private const string URL_ADD_USERS = '/add_users.fcgi?session=';
    private const string URL_EXPORT_CSV = '/export_users_csv.fcgi?session=';
    private const string URL_IMPORT_CSV = '/import_users_csv.fcgi?session=';
    private const string URL_LOAD_USERS = '/load_users.fcgi?session=';
    public DeviceModel $device;

    public function __construct(
        private ?Command $command = null
    ) {
    }

    public function getSeniorDevices(?int $deviceId = null): Collection
    {
        $devices = (new R058RLG('senior_old'))->where('modrlg', 350);
        if ($deviceId) {
            $devices->where('codrlg', $deviceId);
        }
        $devices = $devices->select(
            'codrlg',
            'desrlg',
            DB::raw('regexp_replace(numeip, \'0*([0-9]+)\', \'\\1\') AS numeip')
        );
        return $devices->get();
    }

    public function loadDevices(): DeviceOld2
    {
        foreach ($this->getSeniorDevices() as $device) {
            DeviceModel::updateOrCreate([
                'hcm_id' => $device->codrlg
            ], [
                'hcm_id' => $device->codrlg,
                'name'   => $device->desrlg,
                'ip'     => $device->numeip,
            ]);
        }
        return $this;
    }

    /**
     * @throws \JsonException
     */
    public function loadTemplates(string $employees = null): DeviceOld2
    {
        $sql = " AND 1=1 ";
        if ($employees) {
            $sql = " AND B.NUMCAD IN ($employees) ";
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

            Template::updateOrCreate([
                'hcm_id' => $user->numcad,
            ], [
                'hcm_id'   => $user->numcad,
                'name'     => $data['name'],
                'pis'      => $data['pis'],
                'cpf'      => $user->numcpf,
                'rfid'     => $data['rfid'],
                'template' => json_encode($data, JSON_THROW_ON_ERROR),
            ]);
        }
        return $this;
    }

    /**
     * @throws \JsonException
     */
    public function loadTemplatesAndValidate(): void
    {
//        $this->loadTemplates();

//        $i = 1;
//        foreach ($this->getTemplates() as $template) {
//            dispatch(static function () use ($template) {
//                (new Device())->setDevice((new Device())->getDeviceMaster()->id)->sendEmployee($template->hcm_id);
//            })->onQueue("d-$i");
//            $i = $i === 3 ? 1 : $i + 1;
//        }

//        foreach (array_chunk($this->getTemplates()->pluck('hcm_id')->toArray(), 10) as $batch) {
//            (new Device())->setDevice((new Device())->getDeviceMaster()->id)->sendEmployees($batch);
//            dd(0);
//        }


//        (new Device())->setDevice((new Device())->getDeviceMaster()->id)->clearTemplates();
//
//
//        foreach (array_chunk($this->getTemplates()->pluck('hcm_id')->toArray(), 10) as $batch) {
//            (new Device())->setDevice((new Device())->getDeviceMaster()->id)->sendEmployees($batch);
//            dd(0);
//        }

//        (new Device())->setDevice((new Device())->getDeviceMaster()->id)->clearTemplates();
        (new DeviceOld2())->setDevice((new DeviceOld2())->getDeviceMaster()->id)->sendCsv(
            $this->generateCsvFromTemplate()
        );
    }

    public function generateCsvFromTemplate(): string
    {
        $file = Str::uuid()->toString().'.csv';
        $file = "teste.csv";
        $enter = chr(13).chr(10);
        File::put(
            storage_path("app/private/$file"),
            "pis;nome;administrador;matricula;rfid;codigo;senha;barras;digitais$enter"
        );
        foreach ($this->getTemplates() as $template) {
            $data = json_decode($template->template, false, 512, JSON_THROW_ON_ERROR);
            File::append(
                storage_path("app/private/$file"),
                rtrim(
                    "$data->pis;$data->name;".(int)$data->admin.";$data->registration;$data->rfid;$data->code;$data->password;$data->bars;".implode(
                        '|',
                        $data->templates
                    )
                ).$enter
            );
        }

        return storage_path("app/private/$file");
    }

    public function sendCsv(string $file): void
    {
        $this->login();

        $http = $this->getHttpClient();

        $this->device->refresh();

        $url = "https://".$this->device->ip.static::URL_IMPORT_CSV.$this->device->token;

        $client = new Client([
            'verify'          => false,
            'allow_redirects' => false,
        ]);

        $fileContent = file_get_contents($file);

        $contentLength = strlen($fileContent);

        $response = $client->request('POST', $url, [
            'headers' => [
                'Content-Type'   => 'application/octet-stream',
                'Content-Length' => $contentLength,
                'Expect'         => '',
            ],
            'body'    => $fileContent,
        ]);

        dump('Status: '.$response->getStatusCode());
        dump('Headers:', $response->getHeaders());
        dump('Body:', (string)$response->getBody());
    }

    public function setDeviceMaster(): DeviceOld2
    {
        $this->device = DeviceModel::where('hcm_id', env('DEVICE_MASTER_REP'))->first();
        return $this;
    }

    public function setDevice(int $deviceId): DeviceOld2
    {
        $this->device = DeviceModel::find($deviceId);
        return $this;
    }

    public function setDeviceByIp(string $ip): DeviceOld2
    {
        $this->device = DeviceModel::where('ip', $ip)->first();
        return $this;
    }

    public function getDevice(): DeviceModel
    {
        return $this->device;
    }

    public function getDevices(): Collection
    {
        return DeviceModel::all();
    }

    public function getDeviceMaster(): DeviceModel
    {
        return DeviceModel::where('hcm_id', env('DEVICE_MASTER_REP'))->first();
    }

    public function getTemplates(): Collection
    {
        return Template::whereRaw("1=1")->limit(300)->get();
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withOptions([
            'verify'          => false,
            'connect_timeout' => 0,
            'timeout'         => 0,
        ]);
    }

    /**
     * @throws ConnectionException
     */
    private function validateToken(): bool
    {
        $this->device->refresh();
        if ($this->device->token) {
            $http = $this->getHttpClient();

            $url = "https://".$this->device->ip.self::URL_SESSION_VALIDATION.$this->device->token;

            $response = $http->post($url, null);
            if ($response->successful()) {
                return $response->json('session_is_valid');
            }
        }
        return false;
    }

    /**
     * @throws ConnectionException
     */
    public function login(int $tries = 0): DeviceOld2
    {
        if (!$this->validateToken()) {
            $http = $this->getHttpClient();

            $url = "https://".$this->device->ip.self::URL_LOGIN;
            $body = [
                'login'    => env('DEVICE_USER_REP', 'admin'),
                'password' => env('DEVICE_USER_PASSWORD', 'admin')
            ];

            $response = $http->post($url, $body);

            if (!$response->ok()) {
                Log::channel('biometric')->info("[ERRO] Requisição de login: {$response->body()}");
                if ($tries <= 3) {
                    sleep(1);
                    return $this->login($tries + 1);
                }
                throw new ConnectionException(
                    "Erro ao tentar se conectar ao dispositivo: foram executadas $tries tentativas."
                );
            }

            if ($response->ok()) {
                $this->device->update(['token' => $response->json('session')]);
            }

            return $this;
        }

        return $this;
    }

    private function isMasterDevice(): bool
    {
        return (int)env('DEVICE_MASTER_REP') === (int)$this->device->hcm_id;
    }

    private function validateSendTemplate(Collection $templates): Template
    {
        $validatedTemplatesIds = [];
        foreach ($templates as $template) {
            if ($template->valid) {
                $validatedTemplatesIds[] = $template->hcm_id;
            }

            $deviceTemplate = (new self())->setDeviceMaster()->sendEmployee($template->hcm_id);

            $template = Template::find($deviceTemplate->template_id);

            if ($template->valid) {
                $validatedTemplatesIds[] = $template->hcm_id;
            }
        }

        return Template::where('hcm_id', $validatedTemplatesIds)->get();
    }

    public function sendEmployee(string $hcmId): DeviceTemplate
    {
        $this->loadTemplates($hcmId);

        $template = Template::where('hcm_id', $hcmId)->first();

        if (!$this->isMasterDevice()) {
            $this->validateSendTemplate($template);
        }

        if ($this->isMasterDevice()) {
            $this->deleteTemplate($template);
        }

        return $this->sendTemplate($template);
    }

    public function sendEmployees(array $hcmIds): DeviceTemplate
    {
        $this->loadTemplates(implode(',', $hcmIds));

        $template = Template::whereIn('hcm_id', $hcmIds)->get();

        if (!$this->isMasterDevice()) {
            $template = $this->validateSendTemplate($template);
        }

        if ($this->isMasterDevice()) {
            $this->deleteTemplate($template);
        }

        return $this->sendTemplate($template);
    }

    /**
     * @throws \JsonException
     * @throws ConnectionException
     */
    private function sendTemplate(Collection $templates): DeviceTemplate
    {
        $this->login();

        $http = $this->getHttpClient();

        $this->device->refresh();

        $url = "https://".$this->device->ip.static::URL_ADD_USERS.$this->device->token;

        $payload = [];
        foreach ($templates as $template) {
            $payload[] = json_decode($template->template, true, 512, JSON_THROW_ON_ERROR);
        }

        $payload = [
            'users' => $payload
        ];

        $response = $http->post($url, $payload);

        $deviceTemplate = DeviceTemplate::updateOrCreate([
            'device_id'   => $this->device->id,
            'template_id' => $template->id,
            'type'        => 'add',
        ], [
            'device_id'   => $this->device->id,
            'template_id' => $template->id,
            'type'        => 'add',
            'url'         => $url,
            'http_status' => $response->status(),
            'request'     => json_encode($payload, JSON_THROW_ON_ERROR),
            'response'    => json_encode($response->json(), JSON_THROW_ON_ERROR),
        ]);

        $template->update(['valid' => $response->successful()]);

        return $deviceTemplate;
    }

    /**
     * @throws ConnectionException
     * @throws \JsonException
     */
    private function deleteTemplate(Collection $templates): void
    {
        $this->login();

        $http = $this->getHttpClient();

        $this->device->refresh();

        $url = "https://".$this->device->ip.static::URL_REMOVE_USERS.$this->device->token;

        $payload = [];
        foreach ($templates as $template) {
            if ((int)$template->pis > 0) {
                $payload[] = (int)$template->pis;
            }
        }

        $payload = [
            'users' => $payload
        ];

        $response = $http->post($url, $payload);

//        foreach ($templates as $template) {
//            $deviceTemplate = DeviceTemplate::updateOrCreate([
//                'device_id'   => $this->device->id,
//                'template_id' => $template->id,
//                'type'        => 'delete',
//            ], [
//                'device_id'   => $this->device->id,
//                'template_id' => $template->id,
//                'type'        => 'delete',
//                'url'         => $url,
//                'http_status' => $response->status(),
//                'request'     => json_encode($payload, JSON_THROW_ON_ERROR),
//                'response'    => json_encode($response->json(), JSON_THROW_ON_ERROR),
//            ]);
//
//            $template->update(['valid' => $response->successful()]);
//        }

    }

    private function clearTemplates(): void
    {
        $this->login();

        $http = $this->getHttpClient();

        $this->device->refresh();

        $url = "https://".$this->device->ip.static::URL_EXPORT_CSV.$this->device->token;

        $response = $http->post($url, null);

        $pis = [];
        if ($response->ok()) {
            File::put(storage_path('app/private/export.csv'), $response->body());
            foreach (explode("\r\n", $response->body()) as $line) {
                $pis[] = (object)['pis' => (int)explode(";", $line)[0]];
            }
        }

        $this->deleteTemplate(collect($pis));
    }


}
