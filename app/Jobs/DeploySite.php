<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use App\Support\SiteNginxConfig;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\UniqueFor;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Timeout(600)]
#[UniqueFor(700)]
class DeploySite implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $siteId) {}

    public function uniqueId(): string
    {
        return (string) $this->siteId;
    }

    /**
     * Execute the job.
     */
    public function handle(SSHService $ssh, SiteNginxConfig $nginxConfig): void
    {
        $site = Site::findOrFail($this->siteId)->fresh(['server']);

        if ($site === null) {
            return;
        }

        $server = $site->server;

        if (! is_string($server->host_key) || $server->host_key === '') {
            $this->markFailed($site, 'The server does not have a confirmed SSH host key.');

            return;
        }

        $site->forceFill([
            'status' => 'deploying',
            'failure_message' => null,
        ])->save();

        $host = new HostFingerprint($server->host_key, 'SHA256:confirmed');
        $result = $ssh->run($server, $host, $nginxConfig->installScript($site), 300);

        if (! $result->successful() || ! str_contains($result->output, 'SITE_DEPLOYED:'.$site->domain)) {
            $this->markFailed(
                $site,
                'Nginx configuration failed on the server.',
                trim($result->output.PHP_EOL.$result->errorOutput),
            );

            return;
        }

        if (! $this->verifyHostHeader($site)) {
            $this->markFailed($site, 'Local host-header verification failed after Nginx reload.');

            return;
        }

        $site->forceFill([
            'status' => 'active',
            'failure_message' => null,
            'deployed_at' => now(),
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $site = Site::findOrFail($this->siteId)->fresh(['server']);

        if ($site === null || $site->status === 'active') {
            return;
        }

        $this->markFailed($site, $exception?->getMessage() ?? 'Site deployment failed.');
    }

    private function verifyHostHeader(Site $site): bool
    {
        $marker = $site->headerMarker();

        try {
            $response = Http::timeout(10)
                ->withHeaders(['Host' => $site->domain])
                ->withOptions(['force_ip_resolve' => 'v4'])
                ->get('http://'.$site->server->host.'/');

            return $response->successful() && str_contains($response->body(), $marker);
        } catch (Throwable) {
            return false;
        }
    }

    private function markFailed(Site $site, string $message, ?string $details = null): void
    {
        $site->forceFill([
            'status' => 'failed',
            'failure_message' => $details !== null && $details !== ''
                ? $message.' '.$this->summarize($details)
                : $message,
        ])->save();
    }

    private function summarize(string $details): string
    {
        return mb_substr(preg_replace('/\s+/', ' ', $details) ?? $details, 0, 500);
    }
}
