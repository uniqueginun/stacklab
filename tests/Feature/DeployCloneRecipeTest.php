<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('clone recipe reports git fetch dns failures and removes the partial release', function () {
    $root = cloneRecipeRoot();
    File::ensureDirectoryExists($root.'/bin');
    File::ensureDirectoryExists($root.'/.ssh');
    File::put($root.'/.ssh/deploy_key', 'fake-key');
    File::put($root.'/bin/git', <<<'SH'
#!/usr/bin/env bash
case " $* " in
  *" init "*) mkdir -p "${3:-.}/.git" 2>/dev/null || mkdir -p .git; exit 0 ;;
  *" remote "*) exit 0 ;;
  *" fetch "*)
    echo "ssh: Could not resolve hostname github.com: Temporary failure in name resolution" >&2
    echo "fatal: Could not read from remote repository." >&2
    exit 1
    ;;
esac
exit 0
SH
    );
    chmod($root.'/bin/git', 0755);

    $result = runCloneRecipe($root, 'abc123def');
    $payload = lastJsonLine($result->output().PHP_EOL.$result->errorOutput());

    expect($result->successful())->toBeFalse()
        ->and($payload['success'])->toBeFalse()
        ->and($payload['error']['code'])->toBe('git_fetch_failed')
        ->and($payload['error']['message'])->toContain('Could not resolve hostname github.com')
        ->and(File::isDirectory($root.'/releases/abc123def'))->toBeFalse();
});

test('clone recipe retries leftover directories that still contain git metadata', function () {
    $root = cloneRecipeRoot();
    File::ensureDirectoryExists($root.'/bin');
    File::ensureDirectoryExists($root.'/.ssh');
    File::ensureDirectoryExists($root.'/releases/abc123def/.git');
    File::put($root.'/.ssh/deploy_key', 'fake-key');
    File::put($root.'/bin/git', <<<'SH'
#!/usr/bin/env bash
case " $* " in
  *" init "*) exit 0 ;;
  *" remote "*) exit 0 ;;
  *" fetch "*)
    echo "ssh: Could not resolve hostname github.com: Temporary failure in name resolution" >&2
    exit 1
    ;;
esac
exit 0
SH
    );
    chmod($root.'/bin/git', 0755);

    $result = runCloneRecipe($root, 'abc123def');
    $payload = lastJsonLine($result->output().PHP_EOL.$result->errorOutput());

    expect($payload['error']['code'])->toBe('git_fetch_failed')
        ->and($payload['data']['skipped'] ?? null)->toBeNull()
        ->and(File::isDirectory($root.'/releases/abc123def'))->toBeFalse();
});

test('clone recipe skips a finished release directory', function () {
    $root = cloneRecipeRoot();
    File::ensureDirectoryExists($root.'/bin');
    File::ensureDirectoryExists($root.'/.ssh');
    File::ensureDirectoryExists($root.'/releases/abc123def');
    File::put($root.'/.ssh/deploy_key', 'fake-key');
    File::put($root.'/releases/abc123def/index.php', '<?php');
    File::put($root.'/bin/git', <<<'SH'
#!/usr/bin/env bash
exit 1
SH
    );
    chmod($root.'/bin/git', 0755);

    $result = runCloneRecipe($root, 'abc123def');
    $payload = lastJsonLine($result->output().PHP_EOL.$result->errorOutput());

    expect($result->successful())->toBeTrue()
        ->and($payload['success'])->toBeTrue()
        ->and($payload['data']['skipped'])->toBeTrue();
});

function cloneRecipeRoot(): string
{
    $root = sys_get_temp_dir().'/stacklab-clone-'.uniqid('', true);
    File::ensureDirectoryExists($root);

    register_shutdown_function(function () use ($root): void {
        File::deleteDirectory($root);
    });

    return $root;
}

function runCloneRecipe(string $root, string $commitSha): ProcessResult
{
    $script = File::get(base_path('recipes/_lib.sh')).PHP_EOL
        .'export PATH='.escapeshellarg($root.'/bin').':"$PATH"'.PHP_EOL
        .'export MF_ROOT_PATH='.escapeshellarg($root).PHP_EOL
        .'export MF_REPOSITORY='.escapeshellarg('acme/app').PHP_EOL
        .'export MF_COMMIT_SHA='.escapeshellarg($commitSha).PHP_EOL
        .File::get(base_path('recipes/deploy.clone@v1.sh'));

    return Process::timeout(15)
        ->path($root)
        ->env([
            'PATH' => $root.'/bin:'.getenv('PATH'),
        ])
        ->input($script)
        ->run(['bash', '-s']);
}

/**
 * @return array<string, mixed>
 */
function lastJsonLine(string $output): array
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

    expect($output)->not->toBeEmpty();

    return ['success' => null, 'error' => ['code' => null, 'message' => $output]];
}
