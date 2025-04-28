<?php

namespace App\Services\Clock;

use App\Models\Main\Device;
use Illuminate\Support\Collection;

class DeviceAfd
{
    public function __construct(
        private readonly Device $device
    ) {
    }

    public function load(?string $startDate = null): Collection
    {
        $content = (new DeviceHttp($this->device))->afd($startDate);

        dd($content);

        return collect([]);
    }
}
