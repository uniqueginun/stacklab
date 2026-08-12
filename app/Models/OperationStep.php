<?php

namespace App\Models;

use App\DTO\StepExecutionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string|null $aftermath
 * @property array<string, mixed>|null $arguments
 */
class OperationStep extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Operation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function run(): void
    {
        $this->forceFill([
            'status' => 'running',
            'started_at' => now(),
            'finished_at' => null,
        ])->save();
    }

    public function fail(string $message): void
    {
        $this->forceFill([
            'status' => 'failed',
            'output' => $message,
            'result' => [
                'step_key' => $this->recipe,
                'success' => false,
                'changed' => false,
                'data' => [],
                'error' => [
                    'code' => 'runner_exception',
                    'message' => $message,
                    'details' => null,
                ],
            ],
            'finished_at' => now(),
        ])->save();
    }

    public function resetForRetry(): void
    {
        $this->forceFill([
            'status' => 'pending',
            'output' => null,
            'result' => null,
            'started_at' => null,
            'finished_at' => null,
        ])->save();
    }

    public function markFinished(StepExecutionResult $result): void
    {
        $this->forceFill([
            'status' => $result->getResultStatus(),
            'result' => [
                'step_key' => $result->stepKey,
                'success' => $result->success,
                'changed' => $result->changed,
                'data' => $result->data,
                'error' => $result->error,
            ],
            'output' => $result->output,
            'finished_at' => now(),
        ])->save();
    }
}
