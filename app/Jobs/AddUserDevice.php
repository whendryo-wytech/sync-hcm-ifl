<?php

namespace App\Jobs;

use App\Models\Main\DeviceUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail as MailFacade;

class AddUserDevice implements ShouldQueue
{
    use Queueable;


    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeviceUser $user
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        MailFacade::raw('Hi, welcome user!', static function ($message) {
            $message->to("whendryo@wytech.com.br")->subject('teste');
        });
    }

    public function tags(): array
    {
        return [$this->user->name, $this->user->cpf, $this->user->device->ip, $this->user->device->name];
    }
}
