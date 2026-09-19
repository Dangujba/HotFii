<?php

use App\Http\Controllers\Api\IntegrationTestStatusController;
use App\Http\Controllers\Api\MobileDashboardController;
use App\Http\Controllers\Api\MobileSessionController;
use App\Http\Controllers\Api\MobileVoucherBatchController;
use App\Http\Controllers\Api\NetworkDeviceHeartbeatController;
use App\Http\Controllers\Api\NetworkDeviceWireGuardEnrollController;
use App\Http\Controllers\Api\PortalConfigurationController;
use App\Http\Controllers\Api\ProvisioningDownloadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::prefix('mobile')->name('mobile.')->group(function () {
        Route::post('/auth/login', [MobileSessionController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('auth.login');

        Route::middleware(['auth:sanctum', 'abilities:mobile'])->group(function () {
            Route::get('/session', [MobileSessionController::class, 'show'])->name('session.show');
            Route::delete('/auth/logout', [MobileSessionController::class, 'destroy'])->name('auth.logout');

            Route::prefix('/organizations/{organization}')
                ->middleware('mobile-organization')
                ->name('organizations.')
                ->group(function () {
                    Route::get('/dashboard', MobileDashboardController::class)->name('dashboard');
                    Route::get('/voucher-batches', [MobileVoucherBatchController::class, 'index'])->name('voucher-batches.index');
                    Route::post('/voucher-batches', [MobileVoucherBatchController::class, 'store'])->name('voucher-batches.store');
                    Route::get('/voucher-batches/{batch}', [MobileVoucherBatchController::class, 'show'])->name('voucher-batches.show');
                    Route::patch('/voucher-batches/{batch}', [MobileVoucherBatchController::class, 'update'])->name('voucher-batches.update');
                    Route::delete('/voucher-batches/{batch}', [MobileVoucherBatchController::class, 'destroy'])->name('voucher-batches.destroy');
                    Route::get('/voucher-batches/{batch}/pdf', [MobileVoucherBatchController::class, 'pdf'])->name('voucher-batches.pdf');
                    Route::post('/voucher-batches/{batch}/share', [MobileVoucherBatchController::class, 'share'])->name('voucher-batches.share');
                });
        });
    });

    Route::get('/portal/{device}/configuration', PortalConfigurationController::class)
        ->middleware('throttle:120,1')
        ->name('portal.configuration');

    Route::get('/provisioning/{token}', ProvisioningDownloadController::class)
        ->middleware('throttle:30,1')
        ->name('provisioning.download');

    Route::post('/network-devices/{device}/heartbeat', NetworkDeviceHeartbeatController::class)
        ->middleware('throttle:120,1')
        ->name('network-devices.heartbeat');

    Route::post('/network-devices/{device}/wireguard/enroll', NetworkDeviceWireGuardEnrollController::class)
        ->middleware('throttle:30,1')
        ->name('network-devices.wireguard.enroll');

    Route::get('/network-devices/{device}/integration-tests', [IntegrationTestStatusController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('network-devices.tests.show');

    Route::post('/network-devices/{device}/integration-tests', [IntegrationTestStatusController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('network-devices.tests.store');
});
