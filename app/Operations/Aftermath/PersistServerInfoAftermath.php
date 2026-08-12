<?php

namespace App\Operations\Aftermath;

use App\DTO\StepExecutionResult;
use App\Models\Operation;
use App\Models\OperationStep;
use App\Models\Server;
use App\Operations\Aftermath\Contracts\StepAftermath;

final class PersistServerInfoAftermath implements StepAftermath
{
    public static function key(): string
    {
        return 'persist_server_info';
    }

    public function handle(
        Server $server,
        Operation $operation,
        OperationStep $step,
        StepExecutionResult $result,
    ): void {
        $server->forceFill([
            'server_info' => $result->data,
        ])->save();
    }
}
