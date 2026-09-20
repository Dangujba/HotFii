<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_batches', function (Blueprint $table) {
            $table->foreignId('network_device_id')
                ->nullable()
                ->after('access_plan_id')
                ->constrained('network_devices')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'network_device_id'],
                'voucher_batches_org_device_index'
            );
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('network_device_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('network_devices')
                ->restrictOnDelete();

            $table->index(
                ['organization_id', 'network_device_id'],
                'vouchers_org_device_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_org_device_index');
            $table->dropConstrainedForeignId('network_device_id');
        });

        Schema::table('voucher_batches', function (Blueprint $table) {
            $table->dropIndex('voucher_batches_org_device_index');
            $table->dropConstrainedForeignId('network_device_id');
        });
    }
};
