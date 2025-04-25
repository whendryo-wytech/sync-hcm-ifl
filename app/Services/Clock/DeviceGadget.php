<?php

namespace App\Services\Clock;

use App\Models\Main\Device as DeviceModel;
use App\Models\Senior\R058RLG;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceGadget
{
    public function load(string $devices = null): Collection
    {
        $collection = [];
        foreach ($this->getSenior($devices) as $device) {
            $collection[] = DeviceModel::updateOrCreate([
                'hcm_id' => $device->codrlg
            ], [
                'hcm_id' => $device->codrlg,
                'name'   => $device->desrlg,
                'ip'     => $device->numeip,
            ]);
        }
        return collect($collection);
    }

    private function getSenior(string $devices = null): Collection
    {
        $sql = " 1=1 ";
        if ($devices) {
            $sql = " CODRLG IN ($devices) ";
        }
        return (new R058RLG('senior_old'))
            ->where('modrlg', 350)
            ->whereRaw($sql)
            ->select(
                'codrlg',
                'desrlg',
                DB::raw('regexp_replace(numeip, \'0*([0-9]+)\', \'\\1\') AS numeip')
            )->get();
    }
}
