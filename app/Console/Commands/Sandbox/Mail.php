<?php

namespace App\Console\Commands\Sandbox;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail as MailFacade;

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
        MailFacade::raw('Hi, welcome user!', static function ($message) {
            $message->to("whendryo@wytech.com.br")->subject('teste');
        });
    }
}
