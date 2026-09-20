<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('android');
            $table->string('name', 100);
            $table->char('device_identifier_hash', 64);
            $table->text('push_token')->nullable();
            $table->char('push_token_hash', 64)->nullable()->unique();
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_identifier_hash']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('router_alerts')->default(true);
            $table->boolean('payment_alerts')->default(true);
            $table->boolean('invoice_alerts')->default(true);
            $table->boolean('account_alerts')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'organization_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('mobile_devices');
    }
};
