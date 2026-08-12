<?php

use App\Models\Server;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

test('generateKeyPair maps script json onto server ssh attributes and stores private key contents', function () {
    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake-key-material\n-----END OPENSSH PRIVATE KEY-----\n";
    $privateKeyPath = '/var/www/.ssh/id_ed25519_1_debian-vm';

    $output = json_encode([
        'success' => true,
        'user' => 'www-data',
        'user_home' => '/var/www',
        'remote_host' => '192.168.56.101',
        'port' => 22,
        'key' => [
            'name' => 'id_ed25519_1_debian-vm',
            'type' => 'ED25519',
            'private_key_path' => $privateKeyPath,
            'public_key_path' => '/var/www/.ssh/id_ed25519_1_debian-vm.pub',
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHg8ZghL+8bfqV961OlM8T2Y609EQudj9RE7ct+oDnP9 www-data@uniqueginun',
            'fingerprint' => 'SHA256:d6dHnWotroYvdhGX+BaZ1yCsPwnrwwimgMJxpZO0BHI',
            'generated' => false,
            'already_existed' => true,
        ],
        'known_hosts' => [
            'path' => '/var/www/.ssh/known_hosts',
            'host_key_scanned' => true,
            'updated' => false,
            'already_present' => true,
        ],
        'warning' => '',
    ], JSON_THROW_ON_ERROR);

    Process::fake(function (PendingProcess $process) use ($output, $privateKey) {
        $command = is_array($process->command)
            ? implode(' ', $process->command)
            : (string) $process->command;

        if (str_contains($command, 'setup_ssh_key.sh')) {
            return Process::result(output: $output."\n");
        }

        if (str_contains($command, 'cat')) {
            return Process::result(output: $privateKey);
        }

        return Process::result();
    });

    $server = Server::factory()->create([
        'name' => 'debian-vm',
        'host' => '192.168.56.101',
        'ssh_port' => '22',
    ]);

    $attributes = app(SSHService::class)->generateKeyPair($server);

    expect($attributes)->toBe([
        'host_key_fingerprint' => 'SHA256:d6dHnWotroYvdhGX+BaZ1yCsPwnrwwimgMJxpZO0BHI',
        'known_hosts_path' => '/var/www/.ssh/known_hosts',
        'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHg8ZghL+8bfqV961OlM8T2Y609EQudj9RE7ct+oDnP9 www-data@uniqueginun',
        'ssh_private_key' => $privateKey,
    ]);
});

test('generateKeyPair throws when the script reports failure', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'success' => false,
                'error' => 'User does not exist on this system.',
            ], JSON_THROW_ON_ERROR),
            exitCode: 1,
        ),
    ]);

    $server = Server::factory()->create([
        'name' => 'debian-vm',
        'host' => '192.168.56.101',
        'ssh_port' => '22',
    ]);

    app(SSHService::class)->generateKeyPair($server);
})->throws(RuntimeException::class, 'User does not exist on this system.');

test('verifyConnection writes default-port known_hosts entries without brackets', function () {
    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake-key-material\n-----END OPENSSH PRIVATE KEY-----\n";
    $capturedKnownHosts = null;

    Process::fake(function (PendingProcess $process) use (&$capturedKnownHosts) {
        $command = $process->command;

        if (is_array($command) && ($command[0] ?? null) === 'ssh') {
            $knownHostsOption = collect($command)->first(
                fn (string $part): bool => str_starts_with($part, 'UserKnownHostsFile='),
            );

            expect($knownHostsOption)->not->toBeNull();

            $path = Str::after($knownHostsOption, 'UserKnownHostsFile=');
            $capturedKnownHosts = File::get($path);

            return Process::result(output: 'mini-forge-ok');
        }

        return Process::result();
    });

    $server = Server::factory()->create([
        'host' => '192.168.56.101',
        'ssh_port' => '22',
        'ssh_user' => 'othman',
        'ssh_private_key' => $privateKey,
    ]);

    app(SSHService::class)->verifyConnection(
        $server,
        new HostFingerprint(
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAr3V1qo5SRCZgADLYDk7j0W+gsXLdP9fPXm9kwsL0Pl',
            'SHA256:v6uUKyZza0FLOmIYgBYL5ZJZn5prL/TZpwjBN3XTY60',
        ),
    );

    expect($capturedKnownHosts)->toBe(
        "192.168.56.101 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAr3V1qo5SRCZgADLYDk7j0W+gsXLdP9fPXm9kwsL0Pl\n",
    );
});

test('verifyConnection writes non-default-port known_hosts entries with brackets', function () {
    $privateKey = "-----BEGIN OPENSSH PRIVATE KEY-----\nfake-key-material\n-----END OPENSSH PRIVATE KEY-----\n";
    $capturedKnownHosts = null;

    Process::fake(function (PendingProcess $process) use (&$capturedKnownHosts) {
        $command = $process->command;

        if (is_array($command) && ($command[0] ?? null) === 'ssh') {
            $knownHostsOption = collect($command)->first(
                fn (string $part): bool => str_starts_with($part, 'UserKnownHostsFile='),
            );

            $path = Str::after((string) $knownHostsOption, 'UserKnownHostsFile=');
            $capturedKnownHosts = File::get($path);

            return Process::result(output: 'mini-forge-ok');
        }

        return Process::result();
    });

    $server = Server::factory()->create([
        'host' => '192.168.56.101',
        'ssh_port' => '2222',
        'ssh_user' => 'othman',
        'ssh_private_key' => $privateKey,
    ]);

    app(SSHService::class)->verifyConnection(
        $server,
        new HostFingerprint(
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAr3V1qo5SRCZgADLYDk7j0W+gsXLdP9fPXm9kwsL0Pl',
            'SHA256:v6uUKyZza0FLOmIYgBYL5ZJZn5prL/TZpwjBN3XTY60',
        ),
    );

    expect($capturedKnownHosts)->toBe(
        "[192.168.56.101]:2222 ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIAr3V1qo5SRCZgADLYDk7j0W+gsXLdP9fPXm9kwsL0Pl\n",
    );
});
