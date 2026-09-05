<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Operator\AccessGroupController;
use App\Http\Controllers\Operator\AccessPlanController;
use App\Http\Controllers\Operator\CustomerController;
use App\Http\Controllers\Operator\DashboardController;
use App\Http\Controllers\Operator\FinanceController;
use App\Http\Controllers\Operator\InvoicePaymentController;
use App\Http\Controllers\Operator\LocationController;
use App\Http\Controllers\Operator\NetworkDeviceController;
use App\Http\Controllers\Operator\NotificationController;
use App\Http\Controllers\Operator\OrganizationContextController;
use App\Http\Controllers\Operator\ProvisioningController;
use App\Http\Controllers\Operator\ReportsController;
use App\Http\Controllers\Operator\SalesController;
use App\Http\Controllers\Operator\SessionController;
use App\Http\Controllers\Operator\SettingsController;
use App\Http\Controllers\Operator\TeamController;
use App\Http\Controllers\Operator\VoucherBatchController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\Platform\AuditController as PlatformAuditController;
use App\Http\Controllers\Platform\BillingController as PlatformBillingController;
use App\Http\Controllers\Platform\DashboardController as PlatformDashboardController;
use App\Http\Controllers\Platform\ImpersonationController;
use App\Http\Controllers\Platform\InvoiceController as PlatformInvoiceController;
use App\Http\Controllers\Platform\OrganizationController as PlatformOrganizationController;
use App\Http\Controllers\Platform\PaymentReviewController;
use App\Http\Controllers\Platform\SystemController as PlatformSystemController;
use App\Http\Controllers\Platform\TransactionController as PlatformTransactionController;
use App\Http\Controllers\Platform\UserController as PlatformUserController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store']);
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->middleware('auth')->name('verification.notice');
Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['auth', 'signed', 'throttle:6,1'])->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware(['auth', 'throttle:6,1'])->name('verification.send');

Route::prefix('connect/{device}')->name('portal.')->middleware('throttle:120,1')->group(function () {
    Route::get('/', [PortalController::class, 'show'])->name('show');
    Route::post('/voucher', [PortalController::class, 'redeem'])->name('redeem');
    Route::get('/status', [PortalController::class, 'status'])->name('status');
    Route::get('/connected', [PortalController::class, 'connected'])->name('connected');
    Route::get('/mikrotik/login.html', [PortalController::class, 'mikrotikLogin'])->name('mikrotik-login');
    Route::post('/payment', [PaymentController::class, 'initialize'])->name('payment');
    Route::get('/payment/{transaction}/callback', [PaymentController::class, 'callback'])->name('payment.callback');
    Route::get('/payment/{transaction}/status', [PaymentController::class, 'status'])->name('payment.status');
    Route::get('/payment/{transaction}/poll', [PaymentController::class, 'poll'])->name('payment.poll');
});
Route::post('/webhooks/paystack', PaystackWebhookController::class)->name('webhooks.paystack');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/organizations/{organization}/switch', OrganizationContextController::class)->name('organizations.switch');
});

Route::middleware(['auth', 'verified', 'organization'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/locations', [LocationController::class, 'store'])->middleware('role:owner,manager,technician')->name('locations.store');
    Route::get('/network/devices', [NetworkDeviceController::class, 'index'])->name('network.devices.index');
    Route::post('/network/devices', [NetworkDeviceController::class, 'store'])->middleware('role:owner,manager,technician')->name('network.devices.store');
    Route::get('/network/devices/{device}', [NetworkDeviceController::class, 'show'])->name('network.devices.show');
    Route::post('/network/devices/{device}/test', [NetworkDeviceController::class, 'test'])->middleware('role:owner,manager,technician')->name('network.devices.test');
    Route::post('/network/devices/{device}/provisioning-link', ProvisioningController::class)->middleware('role:owner,manager,technician')->name('network.devices.provisioning-link');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/sessions/{session}/disconnect', [SessionController::class, 'disconnect'])->middleware('role:owner,manager,technician')->name('sessions.disconnect');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('role:owner,manager')->name('customers.store');
    Route::get('/access-groups', [AccessGroupController::class, 'index'])->name('access-groups.index');
    Route::post('/access-groups', [AccessGroupController::class, 'store'])->middleware('role:owner,manager')->name('access-groups.store');
    Route::post('/access-groups/{group}/assign', [AccessGroupController::class, 'assign'])->middleware('role:owner,manager')->name('access-groups.assign');

    Route::get('/plans', [AccessPlanController::class, 'index'])->name('plans.index');
    Route::post('/plans', [AccessPlanController::class, 'store'])->middleware('role:owner,manager')->name('plans.store');

    Route::get('/vouchers', [VoucherBatchController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers', [VoucherBatchController::class, 'store'])->middleware('role:owner,manager,agent')->name('vouchers.store');
    Route::get('/vouchers/{batch}/print', [VoucherBatchController::class, 'print'])->name('vouchers.print');

    Route::get('/sales', [SalesController::class, 'index'])->name('sales.index');
    Route::post('/sales/cash', [SalesController::class, 'store'])->middleware('role:owner,manager,agent')->name('sales.cash.store');
    Route::get('/finance', FinanceController::class)->middleware('role:owner,manager,accountant,viewer')->name('finance.index');
    // Paying the platform's own invoice. Owners and managers only: it moves the
    // organization's money out, unlike the rest of the finance page.
    Route::post('/finance/invoices/{invoice}/pay', [InvoicePaymentController::class, 'initialize'])->middleware('role:owner,manager')->name('finance.invoices.pay');
    Route::get('/finance/invoices/{invoice}/callback', [InvoicePaymentController::class, 'callback'])->name('finance.invoices.callback');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    Route::get('/reports/export/pdf', [ReportsController::class, 'exportPdf'])->name('reports.export.pdf');

    Route::get('/team', [TeamController::class, 'index'])->name('team.index');
    Route::post('/team', [TeamController::class, 'store'])->middleware('role:owner,manager')->name('team.store');
    Route::patch('/team/{member}', [TeamController::class, 'update'])->middleware('role:owner')->name('team.update');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->middleware('role:owner,manager')->name('settings.update');
    Route::post('/settings/payment-profile', [SettingsController::class, 'submitPaymentProfile'])->middleware('role:owner')->name('settings.payment-profile');
});

// The platform owner's console. Deliberately almost entirely read-only: the only
// writes are payment review, support impersonation, and suspending an
// organization. Anything that changes what a customer owes stays out of the UI.
Route::middleware(['auth', 'verified', 'platform-admin'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/', PlatformDashboardController::class)->name('index');

    Route::get('/organizations', [PlatformOrganizationController::class, 'index'])->name('organizations.index');
    // withTrashed: a soft-deleted organization still has money and an audit
    // trail, and the console is the only place left to read them.
    Route::get('/organizations/{organization}', [PlatformOrganizationController::class, 'show'])->withTrashed()->name('organizations.show');
    Route::patch('/organizations/{organization}/status', [PlatformOrganizationController::class, 'status'])->name('organizations.status');

    Route::get('/reviews', [PaymentReviewController::class, 'index'])->name('reviews.index');
    Route::post('/organizations/{organization}/payment/approve', [PaymentReviewController::class, 'approve'])->name('payment.approve');
    Route::post('/organizations/{organization}/payment/reject', [PaymentReviewController::class, 'reject'])->name('payment.reject');

    Route::get('/billing', PlatformBillingController::class)->name('billing.index');
    // The one billing write: recording a transfer that already landed. See
    // Platform\InvoiceController for why this exception was made.
    Route::patch('/invoices/{invoice}/paid', [PlatformInvoiceController::class, 'pay'])->name('invoices.pay');
    Route::get('/transactions', PlatformTransactionController::class)->name('transactions.index');
    Route::get('/users', PlatformUserController::class)->name('users.index');
    Route::get('/audit', PlatformAuditController::class)->name('audit.index');
    Route::get('/system', PlatformSystemController::class)->name('system.index');

    Route::post('/organizations/{organization}/impersonate', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
});