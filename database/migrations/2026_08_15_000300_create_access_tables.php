<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('access_type', 24)->default('paid');
            $table->unsignedBigInteger('price_kobo')->default(0);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->unsignedInteger('download_kbps')->nullable();
            $table->unsignedInteger('upload_kbps')->nullable();
            $table->unsignedSmallInteger('simultaneous_use')->default(1);
            $table->unsignedSmallInteger('validity_days')->nullable();
            $table->boolean('starts_on_first_use')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('customer');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_authenticated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'phone']);
        });

        Schema::create('customer_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('mac_address', 32);
            $table->string('name')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'mac_address']);
        });

        Schema::create('access_groups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('schedule')->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->unsignedInteger('download_kbps')->nullable();
            $table->unsignedInteger('upload_kbps')->nullable();
            $table->unsignedSmallInteger('device_limit')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('access_group_customer', function (Blueprint $table) {
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->primary(['access_group_id', 'customer_id']);
        });

        Schema::create('hotspot_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('access_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('radius_username', 64)->index();
            $table->string('acct_session_id', 64)->nullable()->unique();
            $table->string('mac_address', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->string('status', 24)->default('pending');
            $table->unsignedBigInteger('input_bytes')->default(0);
            $table->unsignedBigInteger('output_bytes')->default(0);
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('stopped_at')->nullable();
            $table->string('terminate_cause')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_sessions');
        Schema::dropIfExists('access_group_customer');
        Schema::dropIfExists('access_groups');
        Schema::dropIfExists('customer_devices');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('access_plans');
    }
};