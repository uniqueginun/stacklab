<?php

namespace App\Actions\Sites;

use App\Jobs\DeploySite;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateSite
{
    /**
     * @param  array{
     *     server: Server,
     *     domain: string,
     *     type: string,
     *     php_version: string,
     *     web_directory: string
     * }  $attributes
     */
    public function handle(User $user, Server $server, array $attributes): Site
    {
        if (! $user->is($server->user)) {
            throw ValidationException::withMessages([
                'server_id' => 'The selected server is invalid.',
            ]);
        }

        if (! $server->canCreateSite()) {
            throw ValidationException::withMessages([
                'server_id' => 'Verify the SSH connection before creating a site.',
            ]);
        }

        $site = $server->sites()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'domain' => $attributes['domain'],
            'type' => $attributes['type'],
            'php_version' => $attributes['php_version'],
            'web_directory' => $attributes['web_directory'],
            'root_path' => '/home/'.$server->ssh_user.'/'.$attributes['domain'],
            'status' => 'pending',
        ]);

        DeploySite::dispatch($site->getKey());

        return $site;
    }
}
