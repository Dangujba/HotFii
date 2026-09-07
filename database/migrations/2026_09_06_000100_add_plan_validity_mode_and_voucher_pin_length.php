<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the validity promised by existing plans and printed stock.
        Schema::table('access_plans', function (Blueprint $table) {
            $table->string('validity_mode', 16)->default('rolling');
        });
        Schema::table('access_plans', function (Blueprint $table) {
            $table->string('validity_mode', 16)->default('midnight')->change();
        });
        Schema::table('voucher_batches', function (Blueprint $table) {
            $table->unsignedTinyInteger('pin_length')->default(12);
        });
    }

    public function down(): void
    {
        Schema::table('voucher_batches', fn (Blueprint $table) => $table->dropColumn('pin_length'));
        Schema::table('access_plans', fn (Blueprint $table) => $table->dropColumn('validity_mode'));
    }
};
