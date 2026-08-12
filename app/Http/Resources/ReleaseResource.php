<?php

namespace App\Http\Resources;

use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Release
 */
class ReleaseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentReleaseId = $this->relationLoaded('site')
            ? $this->site?->current_release_id
            : null;

        return [
            'uuid' => $this->uuid,
            'commit_sha' => $this->commit_sha,
            'commit_message' => $this->commit_message,
            'status' => $this->status,
            'failure_message' => $this->failure_message,
            'deployed_at' => $this->activated_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'is_current' => $currentReleaseId === $this->id,
            'can_rollback' => $this->activated_at !== null && $currentReleaseId !== $this->id,
        ];
    }
}
