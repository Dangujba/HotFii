<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_credentials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('access_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('username', 64)->unique();
            $table->text('password_cipher');
            $table->string('status', 24)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });

        Schema::create('provisioning_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('contact_name');
            $table->string('contact_phone', 32);
            $table->string('bank_name');
            $table->string('account_name');
            $table->text('account_number_cipher');
            $table->string('identity_type', 32);
            $table->text('identity_number_cipher');
            $table->string('status', 24)->default('draft');
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 24)->default('paystack');
            $table->string('provider_reference')->unique();
            $table->unsignedBigInteger('gross_amount_kobo');
            $table->unsignedBigInteger('fees_kobo')->default(0);
            $table->unsignedBigInteger('net_amount_kobo');
            $table->string('status', 24)->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('payment_profiles');
        Schema::dropIfExists('provisioning_tokens');
        Schema::dropIfExists('access_credentials');
    }
};