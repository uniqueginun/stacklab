<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'domain' => $this->domain,
            'type' => $this->type,
            'php_version' => $this->php_version,
            'web_directory' => $this->web_directory,
            'root_path' => $this->root_path,
            'document_root' => $this->documentRoot(),
            'repository' => $this->repository_url,
            'branch' => $this->repository_branch,
            'deployment_options' => $this->resolvedDeploymentOptions(),
            'deploy_key_fingerprint' => $this->deploy_key_fingerprint,
            'status' => $this->status,
            'failure_message' => $this->failure_message,
            'deployed_at' => $this->deployed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'current_release' => $this->when(
                $this->relationLoaded('currentRelease'),
                fn (): ?array => $this->currentRelease === null ? null : [
                    'uuid' => $this->currentRelease->uuid,
                    'commit_sha' => $this->currentRelease->commit_sha,
                ],
            ),
            'server' => $this->when(
                $this->relationLoaded('server'),
                fn (): array => [
                    'uuid' => $this->server->uuid,
                    'name' => $this->server->name,
                    'host' => $this->server->host,
                ],
            ),
        ];
    }
}
