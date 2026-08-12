<?php

namespace App\Actions\Sites;

use App\Actions\Servers\GenerateSshKeyPair;
use App\Models\Site;
use App\Models\User;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use App\Support\GitHubApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttachSiteRepository
{
    public function __construct(
        private GenerateSshKeyPair $generateSshKeyPair,
        private SSHService $ssh,
    ) {}

    /**
     * @param  array{repository: string, branch: string}  $attributes
     */
    public function handle(User $user, Site $site, array $attributes): Site
    {
        $connection = $user->githubConnection;

        if ($connection === null) {
            throw ValidationException::withMessages([
                'repository' => 'Connect GitHub before attaching a repository.',
            ]);
        }

        if ($site->status !== 'active') {
            throw ValidationException::withMessages([
                'repository' => 'Wait until the site Nginx configuration is active.',
            ]);
        }

        if (! is_string($site->server->host_key) || $site->server->host_key === '') {
            throw ValidationException::withMessages([
                'repository' => 'The server does not have a confirmed SSH host key.',
            ]);
        }

        [$owner, $repo] = $this->splitRepository($attributes['repository']);

        $api = new GitHubApi($connection);
        $repository = $api->repository($owner, $repo);
        $branches = collect($api->branches($owner, $repo))->pluck('name');

        if (! $branches->contains($attributes['branch'])) {
            throw ValidationException::withMessages([
                'branch' => 'The selected branch does not exist on this repository.',
            ]);
        }

        $keys = $this->generateSshKeyPair->handle('mini-forge-deploy-'.$site->domain);
        $fingerprint = $this->fingerprint($keys['public_key']);

        if ($site->deploy_key_id !== null && is_string($site->repository_url)) {
            [$previousOwner, $previousRepo] = $this->splitRepository($site->repository_url);
            $api->deleteDeployKey($previousOwner, $previousRepo, (int) $site->deploy_key_id);
        }

        $deployKey = $api->createDeployKey(
            $owner,
            $repo,
            'mini-forge:'.$site->domain,
            $keys['public_key'],
        );

        $host = new HostFingerprint($site->server->host_key, 'SHA256:confirmed');

        $result = $this->ssh->run(
            $site->server,
            $host,
            $this->installScript($site, $keys['private_key']),
            120,
        );

        if (! $result->successful() || ! str_contains($result->output, 'DEPLOY_KEY_INSTALLED')) {
            $api->deleteDeployKey($owner, $repo, $deployKey['id']);

            throw ValidationException::withMessages([
                'repository' => 'Unable to install the deploy key on the server.',
            ]);
        }

        return DB::transaction(function () use ($site, $repository, $attributes, $deployKey, $fingerprint): Site {
            $site->forceFill([
                'repository_url' => $repository['full_name'],
                'repository_id' => $repository['id'],
                'repository_branch' => $attributes['branch'],
                'deploy_key_id' => $deployKey['id'],
                'deploy_key_fingerprint' => $fingerprint !== '' ? $fingerprint : ($deployKey['fingerprint'] ?: null),
            ])->save();

            return $site->fresh() ?? $site;
        });
    }

    /** @return array{0: string, 1: string} */
    private function splitRepository(string $repository): array
    {
        $parts = explode('/', $repository);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ValidationException::withMessages([
                'repository' => 'Repositories must use the owner/name format.',
            ]);
        }

        return [$parts[0], $parts[1]];
    }

    private function fingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey)) ?: [];

        if (count($parts) < 2) {
            return '';
        }

        $decoded = base64_decode($parts[1], true);

        if ($decoded === false) {
            return '';
        }

        return 'SHA256:'.rtrim(strtr(base64_encode(hash('sha256', $decoded, true)), '+/', '-_'), '=');
    }

    private function installScript(Site $site, string $privateKey): string
    {
        $root = escapeshellarg($site->root_path);
        $user = escapeshellarg($site->server->ssh_user);
        $keyB64 = base64_encode($privateKey);
        $isLaravel = $site->type === 'laravel' ? 'true' : 'false';

        return <<<BASH
set -euo pipefail
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:\${PATH:-}"

ROOT={$root}
SSH_USER={$user}
KEY_B64={$keyB64}
IS_LARAVEL={$isLaravel}

sudo -n mkdir -p "\${ROOT}/.ssh" "\${ROOT}/releases" "\${ROOT}/shared"
sudo -n chown -R "\${SSH_USER}:\${SSH_USER}" "\${ROOT}"
sudo -n chmod 700 "\${ROOT}/.ssh"

printf '%s' "\${KEY_B64}" | base64 -d > "\${ROOT}/.ssh/deploy_key"
chmod 600 "\${ROOT}/.ssh/deploy_key"

if [[ ! -s "\${ROOT}/shared/.env" ]]; then
  printf '' > "\${ROOT}/shared/.env"
fi
chmod 640 "\${ROOT}/shared/.env" || true

if [[ "\${IS_LARAVEL}" == "true" ]]; then
  mkdir -p "\${ROOT}/shared/storage/app/public" \
    "\${ROOT}/shared/storage/framework/cache" \
    "\${ROOT}/shared/storage/framework/sessions" \
    "\${ROOT}/shared/storage/framework/views" \
    "\${ROOT}/shared/storage/logs"
fi

path="\${ROOT}"
while [[ "\${path}" != "/" && -n "\${path}" ]]; do
  sudo -n chmod a+x "\${path}"
  path="\$(dirname "\${path}")"
done

echo "DEPLOY_KEY_INSTALLED"
BASH;
    }
}
