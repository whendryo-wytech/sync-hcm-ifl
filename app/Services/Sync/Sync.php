<?php

namespace App\Services\Sync;

use App\Models\Senior\SeniorNew;
use App\Models\Senior\SeniorOld;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

class Sync
{

    /**
     * @throws \JsonException
     */
    public static function run(string $table = null, bool $delete = false): void
    {
        if ($table) {
            static::execute($table, $delete);
            return;
        }
        foreach (static::getTables() as $data) {
            static::execute($data->name, $delete);
        }
    }

    /**
     * @throws \JsonException
     */
    public static function execute(string $table, bool $delete = false): void
    {
        $table = Str::upper($table);
        $tableOriginal = $table;
        $table = Seed::getSiblingTable($table);
        Log::channel('sync')->info("Iniciando integração da tabela $tableOriginal");
        $columns = static::getColumns($table);
        $columnsOriginal = static::getColumns($tableOriginal);
        $tables = collect(static::getTables());
        $type = $tables->where('name', $tableOriginal)->pluck('type')[0];
        Log::channel('sync')->info("Integração do tipo: $type");
        $rows = (new SeniorNew())
            ->setTable($table)
            ->orderBy(DB::raw($columns->keys_raw))
            ->select(DB::raw($columns->raw))
            ->get()
            ->toArray();
        $logData = [];
        $logData['new_rows'] = count($rows);
        $logData['old_insert'] = 0;
        $logData['old_update'] = 0;
        $logData['old_delete'] = 0;
        $logData['execution_start'] = Carbon::now();

        $progressBar = new ProgressBar(new ConsoleOutput(), count($rows));
        $progressBar->start();
        foreach ($rows as $row) {
            try {
                $verify = false;
                $keys = [];
                foreach ($columns->keys as $key) {
                    $key = Str::lower($key);
                    $keys[$key] = $row[$key];
                }

                $instance = (new SeniorOld())->setTable($tableOriginal)->where($keys);

                if ($instance->exists()) {
                    $verify = json_encode(
                            $instance->orderBy(DB::raw($columnsOriginal->keys_raw))
                                ->select(DB::raw($columnsOriginal->raw))
                                ->first()
                                ->toArray(),
                            JSON_THROW_ON_ERROR
                        ) === json_encode($row, JSON_THROW_ON_ERROR);
                }

                if (!$verify) {
                    $data = [];
                    foreach ($columns->columns as $key) {
                        $key = Str::lower($key);
                        $data[$key] = $row[$key];
                    }

                    if ($type === 'DEFAULT' && $instance->exists()) {
                        Log::channel('sync')->info(
                            "Registro encontrado no BD antigo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' iniciando atualização'
                        );
                        $instance->update($data);
                        Log::channel('sync')->info(
                            "Registro encontrado no BD antigo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' atualizado com sucesso!'
                        );
                        ++$logData['old_update'];
                    }
                    if (!$instance->exists()) {
                        Log::channel('sync')->info(
                            "Registro não encontrado no BD antigo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' iniciando a inserção'
                        );

                        if ($type === 'DEFAULT') {
                            $instance->create($data);
                        }

                        if ($type === 'SEED') {
                            Seed::{$tableOriginal}($keys);
                        }

                        Log::channel('sync')->info(
                            "Registro não encontrado no BD antigo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' inserido com sucesso!'
                        );
                        ++$logData['old_insert'];
                    }
                }
            } catch (Exception $e) {
                Log::channel('sync')->error($e->getMessage());
            }
            $progressBar->advance();
        }
        $progressBar->finish();

        if ($delete) {
            $rows = (new SeniorOld())->setTable($table)
                ->select(DB::raw($columns->raw))
                ->get()
                ->toArray();
            $progressBar = new ProgressBar(new ConsoleOutput(), count($rows));
            $progressBar->start();
            foreach ($rows as $row) {
                try {
                    $keys = [];
                    foreach ($columns->keys as $key) {
                        $key = Str::lower($key);
                        $keys[$key] = $row[$key];
                    }
                    $instance = (new SeniorNew())->setTable($table)->where($keys);
                    if (!$instance->exists()) {
                        Log::channel('sync')->info(
                            "Registro não encontrado no BD novo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' iniciando a exclusão'
                        );
                        (new SeniorOld())->setTable($table)->where($keys)->delete();
                        Log::channel('sync')->info(
                            "Registro não encontrado no BD novo: ".json_encode(
                                $keys,
                                JSON_THROW_ON_ERROR
                            ).' excluído com sucesso!'
                        );
                        ++$logData['old_delete'];
                    }
                } catch (Exception $e) {
                    Log::channel('sync')->error($e->getMessage());
                }
            }
        }

        Log::channel('sync')->info("Tabela relacionada: ".$table);
        Log::channel('sync')->info("Qtd. Registros: ".$logData['new_rows']);
        Log::channel('sync')->info("Insert: ".$logData['old_insert']);
        Log::channel('sync')->info("Update: ".$logData['old_update']);
        Log::channel('sync')->info("Delete: ".$logData['old_delete']);
        Log::channel('sync')->info(
            "Tempo de execução: ".Carbon::now()->diff($logData['execution_start'])->format('%H:%I:%S')
        );
        Log::channel('sync')->info("Finalizando integração da tabela $tableOriginal");
        Log::channel('sync')->info("------------------------------------------------------------------------");
    }

    private static function getColumns(string $table): object
    {
        $columnsSeniorOld = collect(Schema::connection('senior_old')->getColumns($table));
        $columnsSeniorNew = collect(Schema::connection('senior_new')->getColumns($table));
        $columnsMerged = [];
        foreach ($columnsSeniorOld as $column) {
            $filteredSeniorNew = $columnsSeniorNew->where('name', $column['name'])->all();
            if (!empty($filteredSeniorNew)) {
                $columnsMerged[] = array_values($filteredSeniorNew)[0];
            }
        }

        $keys = DB::connection('senior_old')->select(
            "    SELECT b.table_name
                              ,b.column_name
                              ,b.position
                              ,a.status
                              ,a.owner
                        FROM all_constraints a
                            ,all_cons_columns b
                        WHERE 1=1
                            AND b.table_name = '$table'
                            AND a.constraint_type = 'P'
                            AND a.constraint_name = b.constraint_name
                            AND a.owner = b.owner
                            AND a.owner = 'RUBI'
                        ORDER BY b.table_name
                                ,b.position"
        );

        $columns = collect($columnsMerged)->pluck('name')->toArray();
        $keys = collect($keys)->pluck('column_name')->toArray();

        return (object)[
            'raw'      => implode(',', $columns),
            'keys'     => $keys,
            'keys_raw' => implode(',', $keys),
            'columns'  => $columns,
            'info'     => $columnsMerged
        ];
    }

    public static function getTables(): object
    {
        $tables = [];
        foreach (explode("\n", Storage::disk('data')->get('tables.txt')) as $data) {
            $data = explode('|', $data);
            if (count($data) >= 3) {
                $tables[$data[2]] = (object)[
                    'name'        => $data[2],
                    'description' => $data[1],
                    'code'        => $data[0],
                    'type'        => $data[3],
                ];
            }
        }
        return (object)$tables;
    }
}
