<?php

namespace App\Models;

use App\Support\SiteDeploymentOptions;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string|null $repository_url
 * @property string|null $repository_branch
 * @property array<string, mixed>|null $deployment_options
 * @property Carbon|null $deployed_at
 * @property Carbon|null $created_at
 */
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime',
            'repository_id' => 'integer',
            'deploy_key_id' => 'integer',
            'deployment_options' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Server, $this> */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /** @return HasMany<Release, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /** @return BelongsTo<Release, $this> */
    public function currentRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'current_release_id');
    }

    public function documentRoot(): string
    {
        $webDirectory = $this->web_directory === '.' ? '' : '/'.trim($this->web_directory, '/');

        if ($this->current_release_id !== null) {
            return $this->root_path.'/current'.$webDirectory;
        }

        return $this->root_path.$webDirectory;
    }

    public function headerMarker(): string
    {
        return 'mini-forge-site:'.$this->uuid;
    }

    /**
     * @return array{
     *     run_composer: bool,
     *     run_npm: bool,
     *     run_migrations: bool,
     *     run_caches: bool,
     *     run_queue_restart: bool,
     *     run_hook: bool
     * }
     */
    public function resolvedDeploymentOptions(): array
    {
        return SiteDeploymentOptions::normalize($this->deployment_options);
    }
}
