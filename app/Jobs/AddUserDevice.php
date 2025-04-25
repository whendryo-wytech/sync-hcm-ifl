<?php

namespace App\Jobs;

use App\Models\Main\DeviceTemplate;
use App\Services\Clock\DevicesOld;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class AddUserDevice implements ShouldQueue
{
    use Queueable;

    public int $timeout = 0;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeviceTemplate|Collection $user
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        DevicesOld::addUser($this->user);
    }

    public function tags(): array
    {
        if ($this->user instanceof Collection) {
            return $this->user->map(fn($user) => $user->name)->toArray();
        }
        return [
            $this->user->name,
            $this->user->cpf,
            $this->user->device->ip,
            $this->user->device->name
        ];
    }
}
