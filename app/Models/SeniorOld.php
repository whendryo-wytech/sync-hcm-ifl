<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeniorOld extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $connection = 'senior_old';
    protected $primaryKey = null;
}
