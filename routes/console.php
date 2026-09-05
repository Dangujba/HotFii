<?php

use App\Jobs\EnforceSubscriptionGrace;
use App\Jobs\ExpireAccessRecords;
use App\Jobs\GenerateMonthlyInvoices;
use App\Jobs\MarkOfflineNetworkDevices;
use App\Jobs\ReconcileRadiusAccounting;
use App\Jobs\SyncUnifiSessions;
use App\Jobs\RecoverPaymentWebhooks;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new MarkOfflineNetworkDevices)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new ExpireAccessRecords)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new RecoverPaymentWebhooks)->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::job(new ReconcileRadiusAccounting)->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::job(new \App\Jobs\SyncUnifiSessions)->everyMinute()->withoutOverlapping();
Schedule::job(new EnforceSubscriptionGrace)->hourly()->withoutOverlapping()->onOneServer();
Schedule::job(new GenerateMonthlyInvoices)->monthlyOn(1, '00:10')->withoutOverlapping()->onOneServer();

Schedule::command('queue:prune-failed --hours=168')->dailyAt('02:00')->onOneServer();
Schedule::command('model:prune')->dailyAt('02:15')->onOneServer();

Artisan::command('hotfii:health', function () {
    $this->components->info('HotFii application booted successfully.');
    $this->line('Database: '.config('database.default'));
    $this->line('Queue: '.config('queue.default'));
    $this->line('Broadcast: '.config('broadcasting.default'));
})->purpose('Show the configured HotFii runtime services');
