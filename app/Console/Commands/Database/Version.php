<?php

namespace App\Console\Commands\Database;

use App\Models\SeniorOld;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Version extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:version';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Senior Old: " . DB::connection('senior_old')->select('SELECT * FROM v$version')[0]->banner);
        $this->info("Senior New: " . DB::connection('senior_new')->select('SELECT * FROM v$version')[0]->banner);
    }
}
