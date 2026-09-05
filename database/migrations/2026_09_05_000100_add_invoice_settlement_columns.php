<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Closes the billing loop: an invoice can now be settled, and a suspension
 * caused by billing can be told apart from one imposed by hand.
 *
 * Before this, GenerateMonthlyInvoices raised invoices nothing could ever mark
 * paid, EnforceSubscriptionGrace suspended organizations for them, and the only
 * way out — the console's reactivate button — was undone on the next hourly run
 * because the overdue invoice was still open.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // How the money arrived: 'paystack' when the organization paid the
            // invoice online, 'manual' when the platform owner recorded a
            // transfer received out of band.
            $table->string('payment_method', 24)->nullable()->after('status');
            // The Paystack reference for an online settlement, or the bank
            // reference typed in by the platform owner for a transfer. Also the
            // key the webhook uses to find the invoice a charge belongs to.
            $table->string('payment_reference')->nullable()->after('payment_method');
            $table->index('payment_reference');
        });

        Schema::table('organizations', function (Blueprint $table) {
            // Set only by EnforceSubscriptionGrace. Its presence is what makes a
            // suspension automatically reversible once the invoice is settled;
            // a suspension applied from the console leaves it null and is never
            // lifted by a job, because a human decided it and a human ends it.
            $table->timestamp('billing_suspended_at')->nullable()->after('live_payments_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_reference']);
            $table->dropColumn(['payment_method', 'payment_reference']);
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('billing_suspended_at');
        });
    }
};
