<?php

namespace App\Models\Base;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ModelSenior extends Model
{
    public $timestamps = false;
    protected $connection = 'senior';
    protected $dateFormat = 'Y-d-m H:i:s';

    public function __construct(?string $connection = null, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setConnection($connection);
        $table = last(explode('\\', static::class));
        $this->setTable($table);
    }

    public function fromDateTime($value)
    {
        return Carbon::parse(parent::fromDateTime($value))->format('Y-d-m H:i:s');
    }

}
