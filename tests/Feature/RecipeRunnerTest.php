<?php

use App\Models\Server;
use App\Models\User;
use App\Operations\RecipeRunner;
use App\Services\SSHService;
use App\Ssh\SshResult;
use Illuminate\Support\Str;

test('recipe runner surfaces git ssh errors when the recipe emits no json', function () {
    $user = User::factory()->create();
    $server = Server::factory()->for($user)->create([
        'host_key' => 'ssh-ed25519 AAAA',
    ]);

    $operation = $server->operations()->create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'deploy',
        'status' => 'running',
        'plan_snapshot' => [],
    ]);

    $step = $operation->steps()->create([
        'position' => 1,
        'name' => 'Clone release',
        'recipe' => 'deploy.clone@v1',
        'status' => 'running',
        'arguments' => [],
    ]);

    $output = <<<'TXT'
Initialized empty Git repository in /home/othman/stacklab.test/releases/abc/.git/

hint: Using 'master' as the name for the initial branch.
ssh: Could not resolve hostname github.com: Temporary failure in name resolution
fatal: Could not read from remote repository.
TXT;

    $this->mock(SSHService::class, function ($mock) use ($output): void {
        $mock->shouldReceive('run')
            ->once()
            ->andReturn(new SshResult(1, $output, ''));
    });

    $result = app(RecipeRunner::class)->run($server, $step);

    expect($result['success'])->toBeFalse()
        ->and($result['error']['message'])->toBe('ssh: Could not resolve hostname github.com: Temporary failure in name resolution');
});
