<?php

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Server;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'uuid' => (string) Str::uuid(),
            'name' => fake()->domainWord().'-'.fake()->numberBetween(1, 99),
            'provider' => fake()->randomElement(['hetzner', 'digitalocean', 'custom']),
            'host' => fake()->ipv4(),
            'ssh_port' => '22',
            'ssh_user' => 'root',
            'ssh_private_key' => '',
            'ssh_public_key' => '',
            'known_hosts_path' => '',
            'host_key_fingerprint' => '',
            'connection_status' => ConnectionStatus::UNVERIFIED,
        ];
    }
}
