<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_profiles', function (Blueprint $table) {
            // Paystack settles to a bank code, not a bank name. Nullable so
            // profiles captured before automatic approval keep working.
            $table->string('bank_code', 16)->nullable()->after('bank_name');

            // Name the bank returned for the account number, which is the
            // evidence automatic approval is based on.
            $table->string('resolved_account_name')->nullable()->after('account_name');

            // Set when Paystack verified the profile without a human review.
            $table->timestamp('auto_approved_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('payment_profiles', function (Blueprint $table) {
            $table->dropColumn(['bank_code', 'resolved_account_name', 'auto_approved_at']);
        });
    }
};
