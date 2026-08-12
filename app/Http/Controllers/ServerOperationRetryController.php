<?php

namespace App\Http\Controllers;

use App\Actions\Operations\ResumeFailedOperation;
use App\Jobs\ProcessOperation;
use App\Models\Operation;
use App\Models\Server;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ServerOperationRetryController extends Controller
{
    public function __invoke(
        Request $request,
        Server $server,
        Operation $operation,
        ResumeFailedOperation $resumeFailedOperation,
    ) {
        abort_unless($server->user_id === $request->user()->id, 404);
        abort_unless($operation->server_id === $server->id, 404);

        $operation = $resumeFailedOperation->handle($operation);

        ProcessOperation::dispatch($operation->getKey());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Retrying failed provisioning step.'),
        ]);

        return to_route('servers.show', $server);
    }
}
