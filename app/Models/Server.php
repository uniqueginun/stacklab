<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['ssh_private_key'];

    protected static function booted(): void
    {
        static::creating(function (Server $server): void {
            $server->uuid ??= (string) Str::uuid();
            $server->connection_status ??= ConnectionStatus::UNVERIFIED;
            $server->user_id ??= Auth::id();
        });
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'server_info' => 'json',
            'connection_status' => ConnectionStatus::class,
            'ssh_private_key' => 'encrypted',
            'verified_at' => 'datetime',
        ];
    }

    protected function scopeConnected(Builder $query): Builder
    {
        return $query->where('connection_status', ConnectionStatus::CONNECTED);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canConnect(): bool
    {
        return $this->connection_status === ConnectionStatus::UNVERIFIED || $this->connection_status === ConnectionStatus::FAILED;
    }

    public function canCreateSite(): bool
    {
        return $this->connection_status === ConnectionStatus::CONNECTED;
    }
}
