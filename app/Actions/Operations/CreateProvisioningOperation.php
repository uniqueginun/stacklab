<?php

namespace App\Actions\Operations;

use App\Enums\ConnectionStatus;
use App\Models\Operation;
use App\Models\Server;
use App\Models\User;
use App\Support\ProvisioningProfiles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProvisioningOperation
{
    public function __construct(private ProvisioningProfiles $profiles) {}

    public function handle(Server $server, User $user, string $profile, ?string $phpVersion = null): Operation
    {
        return DB::transaction(function () use ($server, $user, $profile, $phpVersion) {
            $lockedServer = Server::query()->lockForUpdate()->findOrFail($server->id);

            if ($lockedServer->connection_status !== ConnectionStatus::CONNECTED) {
                throw ValidationException::withMessages([
                    'server' => 'Verify the SSH connection and complete preflight before provisioning.',
                ]);
            }

            if ($lockedServer->operations()->whereIn('status', ['pending', 'running'])->exists()) {
                throw ValidationException::withMessages([
                    'server' => 'This server already has an active operation.',
                ]);
            }

            if ($this->profiles->requiresPhp($profile) && blank($phpVersion)) {
                throw ValidationException::withMessages([
                    'php_version' => 'Select a PHP version for this profile.',
                ]);
            }

            $steps = $this->profiles->steps(
                $profile,
                $this->profiles->requiresPhp($profile) ? $phpVersion : null,
            );

            $operation = $lockedServer->operations()->create([
                'uuid' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'type' => 'provision',
                'plan_snapshot' => [
                    'profile' => $profile,
                    'php_version' => $this->profiles->requiresPhp($profile) ? $phpVersion : null,
                    'steps' => $steps,
                ],
            ]);

            foreach ($steps as $position => $step) {
                $operation->steps()->create([
                    'position' => $position + 1,
                    ...$step,
                ]);
            }

            return $operation;
        }, 5);
    }
}
