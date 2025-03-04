<?php

namespace App\Models\Senior;

use Illuminate\Database\Eloquent\Model;

class SeniorNew extends Model
{
    public $timestamps = false;
    public $incrementing = false;
    protected $connection = 'senior_new';
    protected $primaryKey = null;
}
