<?php

namespace App\Console\Commands\Database;

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
        dd(DB::connection('senior_new')->select('select * from r030emp'));
    }
}
