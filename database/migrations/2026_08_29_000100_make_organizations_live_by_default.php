<?php

use App\Domain\Enums\OrganizationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organizations are operational from registration. Collecting real money is
 * gated on the payment profile instead of on the account status, so the two
 * statuses that only ever meant "waiting to collect money" are folded into
 * Live. Suspended, Grace and PaymentRejected keep their meaning: those are
 * real restrictions rather than an onboarding step.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('status', 32)->default(OrganizationStatus::Live->value)->change();
        });

        // Sandbox and PaymentReview were onboarding states, not restrictions.
        // Anything that never sold keeps trial_started_at null, so billing
        // still skips it.
        DB::table('organizations')
            ->whereIn('status', ['sandbox', 'payment_review'])
            ->update(['status' => OrganizationStatus::Live->value]);
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('status', 32)->default('sandbox')->change();
        });

        // Live organizations that never activated payments were the ones this
        // migration moved out of sandbox.
        DB::table('organizations')
            ->where('status', OrganizationStatus::Live->value)
            ->whereNull('live_payments_enabled_at')
            ->update(['status' => 'sandbox']);
    }
};
