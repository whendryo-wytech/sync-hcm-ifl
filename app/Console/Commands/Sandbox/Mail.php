<?php

namespace App\Console\Commands\Sandbox;

use App\Jobs\AddUserDevice;
use App\Models\Main\DeviceTemplate;
use Illuminate\Console\Command;

class Mail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sandbox:mail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sandbox - Mail';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $user = DeviceTemplate::find(50);
        AddUserDevice::dispatch($user)
            ->onQueue('high')
            ->delay(now()->addSeconds(2));


//        MailFacade::raw('Hi, welcome user!', static function ($message) {
//            $message->to("whendryo@wytech.com.br")->subject('teste');
//        });
    }
}
