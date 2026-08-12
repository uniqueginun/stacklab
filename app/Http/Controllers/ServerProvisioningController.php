<?php

namespace App\Http\Controllers;

use App\Actions\Operations\CreateProvisioningOperation;
use App\Http\Requests\ProvisionServerRequest;
use App\Jobs\ProcessOperation;
use App\Models\Server;
use Inertia\Inertia;

class ServerProvisioningController extends Controller
{
    public function __invoke(
        ProvisionServerRequest $request,
        Server $server,
        CreateProvisioningOperation $createProvisioningOperation,
    ) {
        $operation = $createProvisioningOperation->handle(
            $server,
            $request->user(),
            $request->string('profile')->toString(),
            $request->filled('php_version') ? $request->string('php_version')->toString() : null,
        );

        ProcessOperation::dispatch($operation->getKey());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Provisioning started.'),
        ]);

        return to_route('servers.show', $server);
    }
}
