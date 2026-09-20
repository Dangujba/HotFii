<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_device_bindings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('network_device_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('voucher_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('access_credential_id')
                ->constrained('access_credentials')
                ->cascadeOnDelete();

            $table->string('mac_address', 17);

            $table->string('status', 20)
                ->default('active');

            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            /*
             * A physical/client MAC has one current remembered
             * access identity on a particular HotFii router.
             */
            $table->unique(
                ['network_device_id', 'mac_address'],
                'voucher_device_router_mac_unique'
            );

            $table->index(['voucher_id', 'status']);
            $table->index(['access_credential_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_device_bindings');
    }
};
