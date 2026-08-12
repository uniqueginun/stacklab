<?php

namespace Database\Factories;

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'server_id' => Server::factory(),
            'domain' => $domain,
            'type' => 'laravel',
            'php_version' => '8.3',
            'web_directory' => 'public',
            'root_path' => '/home/forge/'.$domain,
            'status' => 'pending',
        ];
    }

    public function forOwner(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->id,
            'server_id' => Server::factory()->for($user),
        ]);
    }
}
