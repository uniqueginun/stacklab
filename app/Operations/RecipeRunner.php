<?php

namespace App\Operations;

use App\Models\OperationStep;
use App\Models\Server;
use App\Services\SSHService;
use App\Ssh\HostFingerprint;
use Illuminate\Support\Facades\File;
use RuntimeException;

final readonly class RecipeRunner
{
    public function __construct(
        private SSHService $ssh,
    ) {}

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed},
     *     output: string
     * }
     */
    public function run(Server $server, OperationStep $step, int $timeout = 600): array
    {
        // if ($step->recipe === 'deploy.verify@v1') {
        //     return $this->deployHostHeaderVerify->run($server, $step);
        // }

        if (! is_string($server->host_key) || $server->host_key === '') {
            throw new RuntimeException('The server does not have a confirmed SSH host key.');
        }

        $recipePath = base_path('recipes/'.$step->recipe.'.sh');
        $libraryPath = base_path('recipes/_lib.sh');

        if (! File::exists($recipePath)) {
            throw new RuntimeException("Recipe [{$step->recipe}] was not found.");
        }

        $script = File::get($libraryPath)
            .PHP_EOL
            .$this->argumentExports(is_array($step->arguments) ? $step->arguments : [])
            .File::get($recipePath);

        $host = new HostFingerprint($server->host_key, 'SHA256:confirmed');
        $result = $this->ssh->run($server, $host, $script, $timeout);
        $combinedOutput = trim($result->output.($result->errorOutput !== '' ? PHP_EOL.$result->errorOutput : ''));
        $payload = $this->parsePayload($combinedOutput) ?? $this->invalidPayload($step->recipe, $combinedOutput);

        if (! $result->successful() && $payload['success'] === true) {
            $payload['success'] = false;
            $payload['error']['code'] = $payload['error']['code'] ?? 'ssh_exit';
            $payload['error']['message'] = $payload['error']['message'] ?? 'The remote recipe exited with a non-zero status.';
        }

        return [
            ...$payload,
            'output' => $combinedOutput,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function argumentExports(array $arguments): string
    {
        $lines = [];

        foreach ($arguments as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $envKey = 'MF_'.strtoupper((string) $key);
            $lines[] = 'export '.$envKey.'='.escapeshellarg((string) $value);
        }

        return $lines === [] ? '' : implode(PHP_EOL, $lines).PHP_EOL;
    }

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed}
     * }|null
     */
    private function parsePayload(string $output): ?array
    {
        $lines = array_reverse(preg_split('/\R/', trim($output)) ?: []);

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            $decoded = json_decode($line, true);

            if (! is_array($decoded) || ! array_key_exists('success', $decoded)) {
                continue;
            }

            return [
                'step_key' => is_string($decoded['step_key'] ?? null) ? $decoded['step_key'] : 'unknown',
                'success' => (bool) $decoded['success'],
                'changed' => (bool) ($decoded['changed'] ?? false),
                'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
                'error' => [
                    'code' => is_string($decoded['error']['code'] ?? null) ? $decoded['error']['code'] : null,
                    'message' => is_string($decoded['error']['message'] ?? null) ? $decoded['error']['message'] : null,
                    'details' => $decoded['error']['details'] ?? null,
                ],
            ];
        }

        return null;
    }

    /**
     * @return array{
     *     step_key: string,
     *     success: bool,
     *     changed: bool,
     *     data: array<string, mixed>,
     *     error: array{code: string|null, message: string|null, details: mixed}
     * }
     */
    private function invalidPayload(string $recipe, string $combinedOutput): array
    {
        return [
            'step_key' => $recipe,
            'success' => false,
            'changed' => false,
            'data' => [],
            'error' => [
                'code' => 'invalid_recipe_output',
                'message' => $this->inferFailureMessage($combinedOutput) ?? 'The recipe did not return a valid JSON result payload.',
                'details' => $combinedOutput !== '' ? mb_substr($combinedOutput, 0, 2000) : null,
            ],
        ];
    }

    private function inferFailureMessage(string $output): ?string
    {
        $fatal = null;

        foreach (array_reverse(preg_split('/\R/', $output) ?: []) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'ssh:')) {
                return $line;
            }

            if ($fatal === null && str_starts_with($line, 'fatal:')) {
                $fatal = $line;
            }
        }

        return $fatal;
    }
}
