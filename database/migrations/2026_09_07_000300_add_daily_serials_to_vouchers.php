<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'serial_number')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->string('serial_number', 32)
                    ->nullable()
                    ->after('code_last_four');
            });
        }

        if (! Schema::hasTable('voucher_serial_counters')) {
            Schema::create('voucher_serial_counters', function (Blueprint $table) {
                $table->string('serial_date', 8)->primary();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        /*
         * Backfill/normalize existing vouchers.
         *
         * Each calendar day receives:
         * YYYYMMDD-001
         * YYYYMMDD-002
         * ...
         *
         * The sequence is ordered by creation time then voucher ID.
         */
        $timezone = config('hotfii.timezone', 'Africa/Lagos');

        $dailyCounters = [];

        foreach (
            DB::table('vouchers')
                ->select('id', 'created_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->cursor()
            as $voucher
        ) {
            $date = $voucher->created_at
                ? Carbon::parse($voucher->created_at, 'UTC')
                    ->timezone($timezone)
                    ->format('Ymd')
                : now($timezone)->format('Ymd');

            $dailyCounters[$date] =
                ($dailyCounters[$date] ?? 0) + 1;

            DB::table('vouchers')
                ->where('id', $voucher->id)
                ->update([
                    'serial_number' => sprintf(
                        '%s-%03d',
                        $date,
                        $dailyCounters[$date]
                    ),
                ]);
        }

        foreach ($dailyCounters as $date => $lastNumber) {
            DB::table('voucher_serial_counters')
                ->updateOrInsert(
                    ['serial_date' => $date],
                    [
                        'last_number' => $lastNumber,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
        }

        /*
         * PostgreSQL-safe idempotent unique index.
         */
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS vouchers_serial_number_unique
             ON vouchers (serial_number)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS vouchers_serial_number_unique'
        );

        Schema::dropIfExists('voucher_serial_counters');

        if (Schema::hasColumn('vouchers', 'serial_number')) {
            Schema::table('vouchers', function (Blueprint $table) {
                $table->dropColumn('serial_number');
            });
        }
    }
};
