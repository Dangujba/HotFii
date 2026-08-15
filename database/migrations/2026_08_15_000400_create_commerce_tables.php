<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->unique();
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('retail_price_kobo');
            $table->string('status', 24)->default('generated');
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code_lookup', 64)->unique();
            $table->text('code_cipher');
            $table->string('code_last_four', 4);
            $table->string('status', 24)->default('generated');
            $table->unsignedBigInteger('price_snapshot_kobo');
            $table->boolean('is_complimentary')->default(false);
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('provider', 24)->default('paystack');
            $table->string('channel', 24)->default('online');
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('gross_amount_kobo');
            $table->unsignedBigInteger('gateway_fee_kobo')->default(0);
            $table->unsignedBigInteger('platform_fee_kobo')->default(0);
            $table->unsignedBigInteger('billable_sales_kobo')->default(0);
            $table->json('provider_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 24)->default('paystack');
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->json('payload');
            $table->string('status', 24)->default('received');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('fee_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->date('billing_period');
            $table->unsignedBigInteger('billable_sales_kobo');
            $table->unsignedBigInteger('fee_amount_kobo');
            $table->string('status', 24)->default('accrued');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'billing_period']);
            $table->unique(['organization_id', 'source_type', 'source_id']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('plan_code', 40);
            $table->string('status', 24)->default('trial');
            $table->unsignedBigInteger('amount_kobo')->default(0);
            $table->timestamp('current_period_starts_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->date('billing_period');
            $table->unsignedBigInteger('subtotal_kobo');
            $table->unsignedBigInteger('total_kobo');
            $table->string('status', 24)->default('draft');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'billing_period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('fee_ledger_entries');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('voucher_batches');
    }
};