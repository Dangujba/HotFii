<?php

use App\Http\Controllers\Api\IntegrationTestStatusController;
use App\Http\Controllers\Api\MobileAccessPlanController;
use App\Http\Controllers\Api\MobileCustomerController;
use App\Http\Controllers\Api\MobileDashboardController;
use App\Http\Controllers\Api\MobileDeviceController;
use App\Http\Controllers\Api\MobileFinanceController;
use App\Http\Controllers\Api\MobileHotspotSessionController;
use App\Http\Controllers\Api\MobileInvoicePaymentController;
use App\Http\Controllers\Api\MobileNetworkController;
use App\Http\Controllers\Api\MobileNotificationController;
use App\Http\Controllers\Api\MobileReportController;
use App\Http\Controllers\Api\MobileSalesController;
use App\Http\Controllers\Api\MobileSessionController;
use App\Http\Controllers\Api\MobileTwoFactorController;
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
            Route::put('/device', [MobileDeviceController::class, 'update'])->name('device.update');
            Route::delete('/device', [MobileDeviceController::class, 'destroy'])->name('device.destroy');
            Route::post('/auth/two-factor/setup', [MobileTwoFactorController::class, 'store'])->name('auth.two-factor.setup');
            Route::post('/auth/two-factor/confirm', [MobileTwoFactorController::class, 'confirm'])
                ->middleware('throttle:10,1')
                ->name('auth.two-factor.confirm');
            Route::delete('/auth/two-factor', [MobileTwoFactorController::class, 'destroy'])->name('auth.two-factor.destroy');

            Route::prefix('/organizations/{organization}')
                ->middleware('mobile-organization')
                ->name('organizations.')
                ->group(function () {
                    Route::get('/dashboard', MobileDashboardController::class)->name('dashboard');
                    Route::get('/notifications', [MobileNotificationController::class, 'index'])->name('notifications.index');
                    Route::patch('/notifications/preferences', [MobileNotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
                    Route::post('/notifications/read', [MobileNotificationController::class, 'read'])->name('notifications.read');
                    Route::get('/plans', [MobileAccessPlanController::class, 'index'])->name('plans.index');
                    Route::post('/plans', [MobileAccessPlanController::class, 'store'])->name('plans.store');
                    Route::patch('/plans/{plan}', [MobileAccessPlanController::class, 'update'])->name('plans.update');
                    Route::delete('/plans/{plan}', [MobileAccessPlanController::class, 'destroy'])->name('plans.destroy');
                    Route::get('/sales', [MobileSalesController::class, 'index'])->name('sales.index');
                    Route::post('/sales/cash', [MobileSalesController::class, 'store'])->name('sales.cash.store');
                    Route::get('/customers', [MobileCustomerController::class, 'index'])->name('customers.index');
                    Route::get('/customers/{customer}', [MobileCustomerController::class, 'show'])->name('customers.show');
                    Route::get('/routers', [MobileNetworkController::class, 'index'])->name('routers.index');
                    Route::get('/routers/{device}', [MobileNetworkController::class, 'show'])->name('routers.show');
                    Route::post('/routers/{device}/test', [MobileNetworkController::class, 'test'])->name('routers.test');
                    Route::get('/sessions', [MobileHotspotSessionController::class, 'index'])->name('sessions.index');
                    Route::post('/sessions/{session}/disconnect', [MobileHotspotSessionController::class, 'disconnect'])->name('sessions.disconnect');
                    Route::get('/finance', [MobileFinanceController::class, 'index'])->name('finance.index');
                    Route::get('/finance/invoices/{invoice}', [MobileFinanceController::class, 'show'])->name('finance.invoices.show');
                    Route::post('/finance/invoices/{invoice}/pay', [MobileInvoicePaymentController::class, 'initialize'])->name('finance.invoices.pay');
                    Route::get('/reports', [MobileReportController::class, 'index'])->name('reports.index');
                    Route::get('/reports/export/csv', [MobileReportController::class, 'exportCsv'])->name('reports.export.csv');
                    Route::get('/reports/export/pdf', [MobileReportController::class, 'exportPdf'])->name('reports.export.pdf');
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
