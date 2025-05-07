<?php

namespace App\Services\Clock;

use App\Models\Main\Device;
use App\Models\Senior\R058RLG;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeviceGadget
{
    public function load(string $devices = null): Collection
    {
        $collection = [];
        foreach ($this->getSenior($devices) as $device) {
            if ((int)$device->comrlg === 1) {
                $collection[] = Device::updateOrCreate([
                    'hcm_id' => $device->codrlg
                ], [
                    'hcm_id' => $device->codrlg,
                    'name'   => $device->desrlg,
                    'ip'     => $device->numeip,
                ]);
            }
            if ((int)$device->comrlg === 3) {
                Device::where('hcm_id', $device->codrlg)->delete();
            }
        }
        return $this->getDevicesWithoutMaster($devices);
    }

    public function getMaster(): Device
    {
        return Device::where('hcm_id', env('DEVICE_MASTER_REP'))->first();
    }

    public function getDevicesWithoutMaster(string $devices = null, $withSlow = false): Collection
    {
        $sql = " 1=1 ";
        if ($devices) {
            $sql = " hcm_id IN ($devices) ";
        }

        $devices = Device::where('hcm_id', '<>', env('DEVICE_MASTER_REP'))->whereRaw($sql);

        if ($withSlow) {
            $devices = $devices->whereNotIn('hcm_id', explode(',', env('DEVICE_SLOW', '0')));
        }

        return $devices->orderBy('hcm_id')->get();
    }

    public function getDevices(string $devices = null): Collection
    {
        $sql = " 1=1 ";
        if ($devices) {
            $sql = " hcm_id IN ($devices) ";
        }

        return Device::whereRaw($sql)->orderBy('hcm_id')->get();
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
                'comrlg',
                DB::raw('regexp_replace(numeip, \'0*([0-9]+)\', \'\\1\') AS numeip')
            )->get();
    }
}
