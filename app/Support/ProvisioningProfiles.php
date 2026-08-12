<?php

namespace App\Support;

use App\Operations\Aftermath\PersistServerInfoAftermath;
use InvalidArgumentException;

final class ProvisioningProfiles
{
    /**
     * @var array<string, list<array{
     *     name: string,
     *     recipe: string,
     *     aftermath?: string|null,
     *     arguments: array<string, scalar>
     * }>>
     */
    private const PROFILES = [
        'app' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            [
                'name' => 'Fetch server info',
                'recipe' => 'server.info@v1',
                'aftermath' => PersistServerInfoAftermath::class,
                'arguments' => [],
            ],
            ['name' => 'Install PHP', 'recipe' => 'php.install@v1', 'arguments' => []],
            ['name' => 'Install Nginx', 'recipe' => 'nginx.install@v1', 'arguments' => []],
            ['name' => 'Install MySQL', 'recipe' => 'mysql.install@v1', 'arguments' => []],
            ['name' => 'Install Redis', 'recipe' => 'redis.install@v1', 'arguments' => []],
            ['name' => 'Install Node.js', 'recipe' => 'node.install@v1', 'arguments' => []],
            ['name' => 'Install Supervisor', 'recipe' => 'supervisor.install@v1', 'arguments' => []],
            ['name' => 'Verify profile', 'recipe' => 'profile.verify@v1', 'arguments' => []],
        ],
        'web' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            ['name' => 'Install PHP', 'recipe' => 'php.install@v1', 'arguments' => []],
            ['name' => 'Install Nginx', 'recipe' => 'nginx.install@v1', 'arguments' => []],
            ['name' => 'Install Node.js', 'recipe' => 'node.install@v1', 'arguments' => []],
            ['name' => 'Install Supervisor', 'recipe' => 'supervisor.install@v1', 'arguments' => []],
            ['name' => 'Verify profile', 'recipe' => 'profile.verify@v1', 'arguments' => []],
        ],
        'cache' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            ['name' => 'Install Redis', 'recipe' => 'redis.install@v1', 'arguments' => []],
            ['name' => 'Verify profile', 'recipe' => 'profile.verify@v1', 'arguments' => []],
        ],
        'database' => [
            ['name' => 'Verify server access', 'recipe' => 'preflight.check@v1', 'arguments' => []],
            ['name' => 'Install MySQL', 'recipe' => 'mysql.install@v1', 'arguments' => []],
            ['name' => 'Verify profile', 'recipe' => 'profile.verify@v1', 'arguments' => []],
        ],
    ];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::PROFILES);
    }

    /**
     * @return list<array{key: string, label: string, requires_php: bool}>
     */
    public function options(): array
    {
        return collect($this->keys())
            ->map(fn (string $key): array => [
                'key' => $key,
                'label' => match ($key) {
                    'app' => 'App (PHP, Nginx, MySQL, Redis, Node)',
                    'web' => 'Web (PHP, Nginx, Node)',
                    'cache' => 'Cache (Redis)',
                    'database' => 'Database (MySQL)',
                    default => ucfirst($key),
                },
                'requires_php' => $this->requiresPhp($key),
            ])
            ->values()
            ->all();
    }

    public function requiresPhp(string $profile): bool
    {
        foreach ($this->steps($profile) as $step) {
            if ($step['recipe'] === 'php.install@v1') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{
     *     name: string,
     *     recipe: string,
     *     aftermath: string|null,
     *     arguments: array<string, scalar>
     * }>
     */
    public function steps(string $profile, ?string $phpVersion = null): array
    {
        $steps = self::PROFILES[$profile] ?? throw new InvalidArgumentException(
            "Unknown provisioning profile [{$profile}].",
        );

        $normalized = array_map(function (array $step): array {
            $aftermath = $step['aftermath'] ?? null;

            if (is_string($aftermath) && class_exists($aftermath) && method_exists($aftermath, 'key')) {
                $aftermath = $aftermath::key();
            }

            return [
                'name' => $step['name'],
                'recipe' => $step['recipe'],
                'aftermath' => is_string($aftermath) ? $aftermath : null,
                'arguments' => $step['arguments'] ?? [],
            ];
        }, $steps);

        if ($phpVersion === null) {
            return $normalized;
        }

        return array_map(function (array $step) use ($phpVersion): array {
            if ($step['recipe'] !== 'php.install@v1') {
                return $step;
            }

            return [
                ...$step,
                'arguments' => [
                    ...$step['arguments'],
                    'php_version' => $phpVersion,
                ],
            ];
        }, $normalized);
    }
}
