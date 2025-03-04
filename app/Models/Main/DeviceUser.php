<?php

namespace App\Models\Main;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeviceUser extends Model
{
    public function device(): HasOne
    {
        return $this->hasOne(Device::class, 'hcm_id', 'hcm_id');
    }
}
