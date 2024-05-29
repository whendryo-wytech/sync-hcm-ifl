<?php

namespace App\Services\Sync;

use App\Models\SeniorOld;
use Illuminate\Support\Facades\DB;

class Seed
{
    public static function getSiblingTable(string $table): string
    {
        return match ($table) {
            'R034DAC' => 'R034FUN',
            default => $table,
        };
    }

    public static function R034DAC(array $params): void
    {
        $data = [
            'numemp' => $params['numemp'],
            'tipcol' => $params['tipcol'],
            'numcad' => $params['numcad'],
            'verprm' => 'S',
            'codprm' => 125,
            'prmfer' => 125,
            'prmsab' => 125,
            'prmdom' => 125,
            'prmvis' => 124,
            'confai' => 0,
            'conadp' => 'N',
            'concre' => 0,
            'conial' => 'N',
            'temalm' => 0,
            'blofal' => 'N',
            'recvis' => 'S',
            'autagv' => 'N',
            'autasa' => 'S',
            'autext' => 'N',
            'usafro' => 'S',
            'gracon' => 60,
            'conpac' => 'N',
            'tempac' => 0,
            'tolacp' => 0,
            'usabio' => 2,
            'dataso' => DB::Raw("to_date('1900-12-31','YYYY-MM-DD')"),
            'dattse' => DB::Raw("to_date('1900-12-31','YYYY-MM-DD')"),
            'autdbl' => 'N',
            'verafa' => 'S',
            'aprsol' => 'N',
            'monlot' => 'N',
            'conint' => 'N',
            'tolint' => 0,
            'intbdc' => 'N',
            'datinc' => DB::Raw("to_date('1900-12-31','YYYY-MM-DD')"),
            'horinc' => 0,
            'stabdc' => 0,
            'conrea' => 'S',
            'utichv' => 'N',
            'reponl' => 'S',
            'seqreg' => 0,
            'conree' => 'N',
            'usarfa' => 'N'
        ];

        (new SeniorOld())->setTable('R034DAC')->create($data);
    }
}
