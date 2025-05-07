<?php

namespace App\Services\Clock;

use App\Exceptions\DeviceHttpException;
use App\Models\Main\Template;
use App\Models\Senior\R070BIO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

use function Laravel\Prompts\info;

class DeviceTemplate
{

    public function load(string $employees = null): Collection
    {
        $collection = [];
        foreach ($this->getSenior($employees) as $employee) {
            try {
                $collection[] = Template::updateOrCreate([
                    'hcm_id' => $employee['employee']['registration'],
                ], [
                    'hcm_id'   => $employee['data']['registration'],
                    'name'     => $employee['data']['name'],
                    'pis'      => $employee['data']['pis'],
                    'cpf'      => $employee['employee']['cpf'],
                    'rfid'     => $employee['data']['rfid'],
                    'template' => json_encode($employee['data'], JSON_THROW_ON_ERROR),
                ]);
            } catch (Throwable $e) {
                Log::channel('biometric')->info("[ERRO] ".__METHOD__." - ".$e->getMessage());
            }
        }
        return collect($collection);
    }

    /**
     * @throws DeviceHttpException
     */
    public function loadByMaster(): void
    {
        if (false) {
            info(__METHOD__);

            $file = (new DeviceHttp((new DeviceGadget())->getMaster()))->export();

            Template::where('id', '>', 0)->update(['valid' => false]);

            $collection = [];

            info(__METHOD__." Validating templates...");

            $columns = [];
            $firstLine = true;
            foreach (explode(PHP_EOL, file_get_contents($file)) as $line) {
                $data = [];
                foreach (explode(";", $line) as $key => $item) {
                    if ($firstLine && empty($columns[$key])) {
                        $columns[$key] = trim($item);
                    }
                    if (!$firstLine) {
                        $data[$columns[$key]] = $item;
                    }
                }
                if ($data['matricula'] ?? false) {
                    info(__METHOD__." Code $data[matricula] is valid...");
                    Template::where('hcm_id', $data['matricula'])->update(['valid' => true]);
                }
                $firstLine = false;
            }

            Template::where('valid', false)->delete();
        }

        foreach ((new DeviceGadget())->getDevicesWithoutMaster() as $device) {
            $file = (new DeviceHttp($device))->export();

            info(__METHOD__." Sync with ...".$device->id);

            $collection = [];

            $columns = [];
            $firstLine = true;
            foreach (explode(PHP_EOL, file_get_contents($file)) as $line) {
                $data = [];
                foreach (explode(";", $line) as $key => $item) {
                    if ($firstLine && empty($columns[$key])) {
                        $columns[$key] = trim($item);
                    }
                    if (!$firstLine) {
                        $data[$columns[$key]] = $item;
                    }
                }

                if (!Template::where('pis', $data['pis'] ?? '')->exists()) {
                    $collection[] = $data['pis'];
                }

                $firstLine = false;
            }

            try {
                info(__METHOD__." Delete templates ".implode(',', $collection));
                (new DeviceHttp($device))->delete([$collection]);
            } catch (Throwable $e) {
            }

            info(__METHOD__." Send templates...");
            (new DeviceHttp($device))->sendChunk(Template::all(), 100);
        }
    }

    public function getTemplates(string $employees = null, bool $onlyValid = false): Collection
    {
        $sql = " 1=1 ";
        if ($employees) {
            $sql = " HCM_ID IN ($employees) ";
        }

        $template = (new Template());
        if ($onlyValid) {
            $template = $template->where('valid', true);
        }
        $template = $template->whereRaw($sql)->get();

        return collect($template);
    }

    private function getSenior(string $employees = null): Collection
    {
        $collection = [];
        $sql = " AND 1=1 ";
        if ($employees) {
            $sql = " AND B.NUMCAD IN ($employees) ";
        }

        $employees = DB::connection('senior_old')->select(
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

        foreach ($employees as $employee) {
            $templates = (new R070BIO('senior_old'))->where('idtpes', $employee->idtpes)->where('codtbi', 2);
            $collection[] = [
                'data'     => [
                    'name'            => $employee->nomfun,
                    'pis'             => (int)$employee->numpis,
                    'code'            => 0,
                    'templates_count' => $templates->count(),
                    'templates'       => $templates->select()->get()->pluck('tembio')->toArray(),
                    'password'        => "",
                    'admin'           => in_array($employee->numcad, explode(',', env('DEVICE_USERS_ADMIN', '')), true),
                    'rfid'            => (int)$employee->numfis,
                    'bars'            => "",
                    "registration"    => $employee->numcad
                ],
                'employee' => [
                    'name'         => $employee->nomfun,
                    'registration' => $employee->numcad,
                    'cpf'          => $employee->numcpf,
                ],
            ];
        }
        return collect($collection);
    }
}
