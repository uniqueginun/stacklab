<?php

use App\Models\Server;
use App\Models\User;

test('encrypted management private key paths can be stored on the server', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create();

    $privateKeyPath = '/var/www/.ssh/id_ed25519_1_debian-vm';

    $server->update([
        'ssh_private_key' => $privateKeyPath,
        'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIHg8ZghL+8bfqV961OlM8T2Y609EQudj9RE7ct+oDnP9 www-data@uniqueginun',
        'host_key_fingerprint' => 'SHA256:d6dHnWotroYvdhGX+BaZ1yCsPwnrwwimgMJxpZO0BHI',
        'known_hosts_path' => '/var/www/.ssh/known_hosts',
    ]);

    $server->refresh();

    expect($server->ssh_private_key)->toBe($privateKeyPath)
        ->and(strlen((string) $server->getRawOriginal('ssh_private_key')))->toBeGreaterThan(255);
});
