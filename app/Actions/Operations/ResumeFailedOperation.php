<?php

namespace App\Actions\Operations;

use App\Models\Operation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResumeFailedOperation
{
    public function handle(Operation $operation): Operation
    {
        return DB::transaction(function () use ($operation) {
            $locked = Operation::query()->lockForUpdate()->findOrFail($operation->id);

            if ($locked->status !== 'failed') {
                throw ValidationException::withMessages([
                    'operation' => 'Only failed operations can be retried.',
                ]);
            }

            $locked->steps()
                ->whereIn('status', ['failed', 'running'])
                ->get()
                ->each(fn ($step) => $step->resetForRetry());

            $locked->forceFill([
                'status' => 'pending',
                'failure_message' => null,
                'finished_at' => null,
            ])->save();

            return $locked->fresh(['steps']);
        }, 5);
    }
}
