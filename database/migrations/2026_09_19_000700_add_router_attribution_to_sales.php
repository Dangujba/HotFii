<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('activated_network_device_id')
                ->nullable()
                ->after('network_device_id')
                ->constrained('network_devices')
                ->nullOnDelete();

            $table->index(
                ['organization_id', 'activated_network_device_id'],
                'vouchers_org_activated_device_index'
            );
        });

        Schema::table('fee_ledger_entries', function (Blueprint $table) {
            $table->foreignId('network_device_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('network_devices')
                ->nullOnDelete();

            $table->index(
                ['organization_id', 'network_device_id', 'billing_period'],
                'fee_ledger_org_device_period_index'
            );
        });

        $this->backfillVoucherRouters();
        $this->backfillTransactionLedgerRouters();
    }

    private function backfillVoucherRouters(): void
    {
        DB::table('vouchers')
            ->whereNotNull('activated_at')
            ->orderBy('id')
            ->chunkById(250, function ($vouchers): void {
                foreach ($vouchers as $voucher) {
                    $deviceId = $voucher->network_device_id;
                    $reference = 'HF-VCH-'.Str::upper(str_replace('-', '', $voucher->uuid));

                    if ($deviceId === null) {
                        $deviceId = DB::table('transactions')
                            ->where('organization_id', $voucher->organization_id)
                            ->where('reference', $reference)
                            ->value('network_device_id');
                    }

                    if ($deviceId === null) {
                        $deviceId = DB::table('voucher_device_bindings')
                            ->where('voucher_id', $voucher->id)
                            ->orderBy('first_seen_at')
                            ->value('network_device_id');
                    }

                    if ($deviceId === null) {
                        $deviceId = DB::table('access_credentials')
                            ->join('hotspot_sessions', function ($join): void {
                                $join->on('hotspot_sessions.organization_id', '=', 'access_credentials.organization_id')
                                    ->on('hotspot_sessions.radius_username', '=', 'access_credentials.username');
                            })
                            ->where('access_credentials.voucher_id', $voucher->id)
                            ->orderByRaw('COALESCE(hotspot_sessions.started_at, hotspot_sessions.created_at)')
                            ->value('hotspot_sessions.network_device_id');
                    }

                    if ($deviceId === null) {
                        continue;
                    }

                    DB::table('vouchers')
                        ->where('id', $voucher->id)
                        ->update(['activated_network_device_id' => $deviceId]);

                    DB::table('transactions')
                        ->where('organization_id', $voucher->organization_id)
                        ->where('reference', $reference)
                        ->whereNull('network_device_id')
                        ->update(['network_device_id' => $deviceId]);

                    DB::table('fee_ledger_entries')
                        ->where('organization_id', $voucher->organization_id)
                        ->where('source_type', 'voucher')
                        ->where('source_id', $voucher->id)
                        ->whereNull('network_device_id')
                        ->update(['network_device_id' => $deviceId]);
                }
            });
    }

    private function backfillTransactionLedgerRouters(): void
    {
        DB::table('fee_ledger_entries')
            ->where('source_type', 'transaction')
            ->whereNull('network_device_id')
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $deviceId = DB::table('transactions')
                        ->where('id', $entry->source_id)
                        ->value('network_device_id');

                    if ($deviceId !== null) {
                        DB::table('fee_ledger_entries')
                            ->where('id', $entry->id)
                            ->update(['network_device_id' => $deviceId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('fee_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('fee_ledger_org_device_period_index');
            $table->dropConstrainedForeignId('network_device_id');
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex('vouchers_org_activated_device_index');
            $table->dropConstrainedForeignId('activated_network_device_id');
        });
    }
};
