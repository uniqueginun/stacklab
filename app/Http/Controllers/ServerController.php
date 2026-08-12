<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use App\Models\Server;
use App\Support\PhpVersions;
use App\Support\ProvisioningProfiles;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServerController extends Controller
{
    public function index(Request $request)
    {
        $servers = $request->user()
            ->servers()
            ->latest()
            ->get(['uuid', 'name', 'host', 'ssh_port', 'provider', 'connection_status']);

        return Inertia::render('servers/Index', [
            'servers' => $servers,
        ]);
    }

    public function create()
    {
        $providers = config('servers.providers');

        return Inertia::render('servers/Create', [
            'providers' => $providers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:255',
            'host' => 'required|ipv4',
            'ssh_port' => 'required|integer|min:1|max:65535',
            'ssh_user' => 'required|string|max:255',
        ]);

        $server = Server::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Server created successfully.')]);

        return to_route('servers.show', $server);
    }

    public function show(Request $request, Server $server, ProvisioningProfiles $profiles)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        $candidate = session()->get(ServerSSHController::sessionKey($server));

        $operation = $server->operations()
            ->where('type', 'provision')
            ->latest('id')
            ->with(['steps' => fn ($query) => $query->orderBy('position')])
            ->first();

        return Inertia::render('servers/Show', [
            'server' => [
                'uuid' => $server->uuid,
                'name' => $server->name,
                'host' => $server->host,
                'ssh_port' => $server->ssh_port,
                'ssh_user' => $server->ssh_user,
                'provider' => $server->provider,
                'connection_status' => $server->connection_status->value,
                'ssh_public_key' => $server->ssh_public_key,
                'host_key_fingerprint' => $server->host_key_fingerprint,
                'verified_at' => $server->verified_at?->toIso8601String(),
                'has_management_key' => filled($server->ssh_public_key),
                'can_connect' => $server->canConnect(),
                'profile' => $server->profile,
            ],
            'discoveredFingerprint' => is_array($candidate) && is_string($candidate['fingerprint'] ?? null)
                ? $candidate['fingerprint']
                : null,
            'operation' => $this->serializeOperation($operation),
            'provisioningProfiles' => $profiles->options(),
            'phpVersions' => PhpVersions::all(),
            'defaultPhpVersion' => PhpVersions::default(),
        ]);
    }

    /**
     * @return array{
     *     uuid: string,
     *     type: string,
     *     status: string,
     *     profile: string|null,
     *     php_version: string|null,
     *     failure_message: string|null,
     *     started_at: string|null,
     *     finished_at: string|null,
     *     steps: list<array{
     *         id: int,
     *         position: int,
     *         name: string,
     *         recipe: string,
     *         status: string,
     *         output: string|null,
     *         started_at: string|null,
     *         finished_at: string|null
     *     }>
     * }|null
     */
    private function serializeOperation(?Operation $operation): ?array
    {
        if ($operation === null) {
            return null;
        }

        return [
            'uuid' => $operation->uuid,
            'type' => $operation->type,
            'status' => $operation->status,
            'profile' => $operation->getProfile(),
            'php_version' => is_string(data_get($operation->plan_snapshot, 'php_version'))
                ? data_get($operation->plan_snapshot, 'php_version')
                : null,
            'failure_message' => $operation->failure_message,
            'started_at' => $operation->started_at?->toIso8601String(),
            'finished_at' => $operation->finished_at?->toIso8601String(),
            'steps' => $operation->steps->map(fn ($step): array => [
                'id' => $step->id,
                'position' => $step->position,
                'name' => $step->name,
                'recipe' => $step->recipe,
                'status' => $step->status,
                'output' => $step->output,
                'started_at' => $step->started_at?->toIso8601String(),
                'finished_at' => $step->finished_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
