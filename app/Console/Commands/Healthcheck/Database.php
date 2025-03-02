<?php

namespace App\Console\Commands\Healthcheck;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Database extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'healthcheck:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Healthcheck for database';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $vers = [
            'pgsql' => (object)['conn' => 'pgsql','name' => 'Sync','sql' => 'SELECT version()'],
            'senior_new' => (object)['conn' => 'senior_new','name' => 'Senior New','sql' => 'SELECT banner as version FROM V$VERSION'],
            'senior_old' => (object)['conn' => 'senior_old','name' => 'Senior Old','sql' => 'SELECT banner as version FROM V$VERSION'],
        ];

        foreach ($vers as $ver) {
            $this->warn("[$ver->name]");
            try{
                $data = DB::connection($ver->conn)->select($ver->sql);
                $this->info($data[0]->version);
            } catch (Exception $e) {
                $this->error($e->getMessage());
            }
            $this->newLine();
        }

    }
}
