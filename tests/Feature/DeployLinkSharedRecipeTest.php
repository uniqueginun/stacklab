<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('link shared generates an app key without composer vendor', function () {
    $root = linkSharedRoot();
    $release = $root.'/releases/abc123def';
    File::ensureDirectoryExists($release);
    File::put($release.'/artisan', "<?php\n");
    File::put($release.'/.env.example', "APP_NAME=Laravel\nAPP_KEY=\nAPP_URL=\n");

    $result = runLinkSharedRecipe($root, 'abc123def');
    $payload = linkSharedJson($result->output().PHP_EOL.$result->errorOutput());
    $env = File::get($root.'/shared/.env');

    expect($result->successful())->toBeTrue()
        ->and($payload['success'])->toBeTrue()
        ->and($env)->toMatch('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m')
        ->and(File::isFile($release.'/.env'))->toBeTrue();
});

test('link shared does not overwrite an existing app key', function () {
    $root = linkSharedRoot();
    $release = $root.'/releases/abc123def';
    File::ensureDirectoryExists($release);
    File::ensureDirectoryExists($root.'/shared');
    File::put($release.'/artisan', "<?php\n");
    File::put($root.'/shared/.env', "APP_KEY=base64:existingkey\n");

    $result = runLinkSharedRecipe($root, 'abc123def');

    expect($result->successful())->toBeTrue()
        ->and(File::get($root.'/shared/.env'))->toContain('APP_KEY=base64:existingkey');
});

function linkSharedRoot(): string
{
    $root = sys_get_temp_dir().'/stacklab-link-'.uniqid('', true);
    File::ensureDirectoryExists($root);

    register_shutdown_function(function () use ($root): void {
        File::deleteDirectory($root);
    });

    return $root;
}

function runLinkSharedRecipe(string $root, string $commitSha): ProcessResult
{
    $script = File::get(base_path('recipes/_lib.sh')).PHP_EOL
        .'export MF_ROOT_PATH='.escapeshellarg($root).PHP_EOL
        .'export MF_COMMIT_SHA='.escapeshellarg($commitSha).PHP_EOL
        .'export MF_SITE_TYPE='.escapeshellarg('laravel').PHP_EOL
        .'export MF_DOMAIN='.escapeshellarg('app.example.test').PHP_EOL
        .'export MF_PHP_VERSION='.escapeshellarg('8.3').PHP_EOL
        .File::get(base_path('recipes/deploy.link_shared@v1.sh'));

    return Process::timeout(15)
        ->path($root)
        ->input($script)
        ->run(['bash', '-s']);
}

/**
 * @return array<string, mixed>
 */
function linkSharedJson(string $output): array
{
    foreach (array_reverse(preg_split('/\R/', trim($output)) ?: []) as $line) {
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);

        if (is_array($decoded) && array_key_exists('success', $decoded)) {
            return $decoded;
        }
    }

    return ['success' => null, 'error' => ['message' => $output]];
}
