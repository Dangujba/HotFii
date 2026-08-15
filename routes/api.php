<?php

use App\Http\Controllers\Api\IntegrationTestStatusController;
use App\Http\Controllers\Api\NetworkDeviceHeartbeatController;
use App\Http\Controllers\Api\PortalConfigurationController;
use App\Http\Controllers\Api\ProvisioningDownloadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/portal/{device}/configuration', PortalConfigurationController::class)
        ->middleware('throttle:120,1')
        ->name('portal.configuration');

    Route::get('/provisioning/{token}', ProvisioningDownloadController::class)
        ->middleware('throttle:30,1')
        ->name('provisioning.download');

    Route::post('/network-devices/{device}/heartbeat', NetworkDeviceHeartbeatController::class)
        ->middleware('throttle:120,1')
        ->name('network-devices.heartbeat');

    Route::get('/network-devices/{device}/integration-tests', [IntegrationTestStatusController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('network-devices.tests.show');

    Route::post('/network-devices/{device}/integration-tests', [IntegrationTestStatusController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('network-devices.tests.store');
});