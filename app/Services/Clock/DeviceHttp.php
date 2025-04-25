<?php

namespace App\Services\Clock;

use App\Exceptions\DeviceHttpException;
use App\Models\Main\Device;
use GuzzleHttp\Client;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeviceHttp
{
    private const string URL_LOGIN = '/login.fcgi';
    private const string URL_SESSION_VALIDATION = '/session_is_valid.fcgi?session=';
    private const string URL_REMOVE_USERS = '/remove_users.fcgi?session=';
    private const string URL_ADD_USERS = '/add_users.fcgi?session=';
    private const string URL_EXPORT_CSV = '/export_users_csv.fcgi?session=';
    private const string URL_IMPORT_CSV = '/import_users_csv.fcgi?session=';
    private const string URL_LOAD_USERS = '/load_users.fcgi?session=';

    public function __construct(
        private readonly Device $device
    ) {
    }

    public function sendChunk(Collection $templates, int $chunk)
    {
        foreach ($templates->chunk($chunk) as $chunked) {
            $this->send($chunked);
        }
    }

    public function send(Collection $templates): void
    {
        $client = new Client([
            'verify'          => false,
            'allow_redirects' => false,
        ]);

        $file = $this->saveTemplateFile($templates);

        $fileContent = file_get_contents($file);

        $contentLength = strlen($fileContent);

        $response = $client->request('POST', $this->url(static::URL_IMPORT_CSV), [
            'headers' => [
                'Content-Type'   => 'application/octet-stream',
                'Content-Length' => $contentLength,
                'Expect'         => '',
            ],
            'body'    => $fileContent,
        ]);

        if ($response->getStatusCode() !== 200) {
            new DeviceHttpException($response->getBody()->getContents(), $response->getStatusCode(), $response);
        }
    }

    public function export(): string
    {
        $this->login();

        $http = $this->getHttpClient();

        $this->device->refresh();

        $url = "https://".$this->device->ip.static::URL_EXPORT_CSV.$this->device->token;

        $response = $http->post($url, null);

        $file = storage_path('app/private/'.Str::uuid()->toString().'.csv');
        if ($response->ok()) {
            File::put($file, $response->body());
        }
        return $file;
    }

    public function clear(): DeviceHttp
    {
        try {
            $pis = [];
            foreach (explode("\r\n", File::get($this->export())) as $line) {
                $pis[] = (int)explode(";", $line)[0];
            }

            return $this->delete($pis);
        } catch (Throwable $e) {
            new DeviceHttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function delete(array $pis): DeviceHttp
    {
        try {
            $payload = [];
            foreach ($pis as $number) {
                if ((int)$number > 0) {
                    $payload[] = (int)$number;
                }
            }

            $payload = [
                'users' => $payload
            ];

            $http = $this->getHttpClient();

            $response = $http->post($this->url(static::URL_REMOVE_USERS), $payload);

            if (!$response->successful()) {
                Log::channel('biometric')->info(
                    "[ERRO] ".__METHOD__." - ".json_encode(
                        $response->body(),
                        JSON_THROW_ON_ERROR
                    )
                );
            }
            return $this;
        } catch (Throwable $e) {
            new DeviceHttpException($e->getMessage(), $e->getCode(), $e);
        }

        return $this;
    }

    private function url(string $type): string
    {
        $this->login();

        $this->device->refresh();

        return "https://".match ($type) {
                static::URL_REMOVE_USERS => $this->device->ip.static::URL_REMOVE_USERS.$this->device->token,
                static::URL_IMPORT_CSV => $this->device->ip.static::URL_IMPORT_CSV.$this->device->token,
                default => throw new DeviceHttpException('Type not found')
            };
    }

    private function getHttpClient(): PendingRequest
    {
        return Http::withOptions([
            'verify'          => false,
            'connect_timeout' => 0,
            'timeout'         => 0,
        ]);
    }

    private function saveTemplateFile(Collection $templates): string
    {
        $file = Str::uuid()->toString().'.csv';
        $enter = chr(13).chr(10);
        File::put(
            storage_path("app/private/$file"),
            "pis;nome;administrador;matricula;rfid;codigo;senha;barras;digitais$enter"
        );

        try {
            foreach ($templates as $template) {
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
        } catch (Throwable $e) {
            new DeviceHttpException($e->getMessage(), $e->getCode(), $e);
        }

        return storage_path("app/private/$file");
    }

    private function login(int $tries = 0): DeviceHttp
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

}
