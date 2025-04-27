<?php

namespace App\Services\Clock;

use App\Exceptions\DevicePendencyException;
use App\Models\Senior\SeniorOld;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class DevicePendency
{
    public function setByPendencies()
    {
    }

    /**
     * @throws \JsonException
     * @throws DevicePendencyException
     */
    public function getPendencies(): Collection
    {
        try {
            $employees = [];
            $pendencies = [];
            foreach (
                (new SeniorOld())->setTable('RTC_PENDENCIES')
                    ->whereIn('TABLENAME', ['R070BIO', 'R070CON'])
                    ->get() as $pendency
            ) {
                $keys = (array)json_decode(Str::lower($pendency->recordkey), false, 512, JSON_THROW_ON_ERROR);
                if ($keys['idtpes'] ?? false) {
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
                    $pendencies[$pendency->id] = false;
                    if ($employee[0] ?? false) {
                        $employees[$employee[0]->numcad] = $employee[0];
                        $pendencies[$pendency->id] = true;
                    }
                }
            }

            return collect([
                'templates'  => (new DeviceTemplate())->getTemplates(implode(',', array_keys($employees))),
                'employees'  => $employees,
                'pendencies' => $pendencies,
            ]);
        } catch (Throwable $e) {
            throw new DevicePendencyException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
