<?php

namespace App\Http\Controllers;

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class ServerSSHController extends Controller
{
    // Steps
    // generates a management keypair and stores both on the server record (private_key encrypted, public_key shown in UI). Status is not ready yet; host_key is still empty.

    // You install the public key on the VM
    // Put public_key into ~/.ssh/authorized_keys for ssh_user, and set up passwordless sudo. Mini-Forge never uses your password.

    // Click “Verify connection” (discover)
    // App runs ssh-keyscan against host:port, picks a host key (prefers ed25519), computes its SHA256:… fingerprint, and puts candidate {key, fingerprint} in the session only.

    // UI shows that fingerprint
    // You compare it on the VM (e.g. ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub). This is TOFU: “is this the machine I think it is?”

    // Click “Trust and verify SSH” (confirm)
    // App rebuilds the session candidate as HostFingerprint, then SSHs with:

    // management private key
    // StrictHostKeyChecking against that exact host key
    // command: printf mini-forge-ok && sudo -n true
    // That proves: right host + key auth works + passwordless sudo works.

    // On success
    // Persist host_key, set status = connected, clear session. Later SSH uses the saved host key.

    // On failure at step 3 or 5 → error flash, nothing trusted in the DB.

    // So: keygen = “who Mini-Forge is”; confirmation fingerprint = “who the server claims to be,” then a live login check before trust is saved.

    public function __construct(private SSHService $sshService) {}

    public static function sessionKey(Server $server): string
    {
        return 'servers.connection.'.$server->uuid;
    }

    public function connect(Request $request, Server $server)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        if (! $server->canConnect()) {
            abort(400, 'Server is not ready to connect or already connected.');
        }

        try {
            $server->update($this->sshService->generateKeyPair($server));

            return to_route('servers.show', $server);
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function verify(Request $request, Server $server)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        try {
            $hostFingerprint = $this->sshService->discoverHost($server);

            session()->put(self::sessionKey($server), [
                'key' => $hostFingerprint->key,
                'fingerprint' => $hostFingerprint->fingerprint,
            ]);

            return to_route('servers.show', $server);
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }
    }

    public function confirm(Request $request, Server $server)
    {
        abort_unless($server->user_id === $request->user()->id, 404);

        $candidate = session()->get(
            $key = self::sessionKey($server)
        );

        if (! is_array($candidate) || ! is_string($candidate['key'] ?? null) || ! is_string($candidate['fingerprint'] ?? null)) {
            return back()->withErrors(['connection' => 'Discover the server fingerprint again before confirming it.']);
        }

        $host = new HostFingerprint($candidate['key'], $candidate['fingerprint']);

        try {
            $this->sshService->verifyConnection($server, $host);
        } catch (RuntimeException $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        } catch (Throwable $e) {
            return back()->withErrors(['message' => $e->getMessage()]);
        }

        $server->update([
            'host_key' => $host->key,
            'host_key_fingerprint' => $host->fingerprint,
            'connection_status' => ConnectionStatus::CONNECTED,
            'verified_at' => now(),
        ]);

        session()->forget($key);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('SSH connection verified.')]);

        return to_route('servers.show', $server);
    }
}
