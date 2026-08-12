<?php

use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerOperationRetryController;
use App\Http\Controllers\ServerProvisioningController;
use App\Http\Controllers\ServerSSHController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SiteDeployController;
use App\Http\Controllers\SiteRepositoryController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('servers', [ServerController::class, 'index'])->name('servers.index');
    Route::get('servers/create', [ServerController::class, 'create'])->name('servers.create');
    Route::post('servers', [ServerController::class, 'store'])->name('servers.store');
    Route::get('servers/{server:uuid}', [ServerController::class, 'show'])->name('servers.show');

    Route::post('servers/{server:uuid}/ssh-connect', [ServerSSHController::class, 'connect'])->name('servers.ssh.connect');
    Route::post('servers/{server:uuid}/ssh-verify', [ServerSSHController::class, 'verify'])->name('servers.ssh.verify');
    Route::post('servers/{server:uuid}/ssh-confirm', [ServerSSHController::class, 'confirm'])->name('servers.ssh.confirm');

    Route::post('servers/{server:uuid}/provision', ServerProvisioningController::class)->name('servers.provision');
    Route::post('servers/{server:uuid}/operations/{operation:uuid}/retry', ServerOperationRetryController::class)
        ->name('servers.operations.retry');

    Route::get('sites/{site}/repository', [SiteRepositoryController::class, 'edit'])->name('sites.repository.edit');
    Route::put('sites/{site}/repository', [SiteRepositoryController::class, 'update'])->name('sites.repository.update');

    Route::post('sites/{site}/deployments', [SiteDeployController::class, 'store'])->name('sites.deployments.store');

    Route::resource('sites', SiteController::class)->only(['index', 'create', 'store', 'show']);
});

require __DIR__.'/settings.php';
