<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_sessions', function (Blueprint $table) {
            $table->string('source', 24)->default('radius')->index();
            $table->string('external_session_id', 191)->nullable();
            $table->json('session_meta')->nullable();

            $table->index(
                ['network_device_id', 'source', 'external_session_id'],
                'hotspot_sessions_external_lookup'
            );
        });

        // API/controller-backed sessions such as UniFi do not necessarily
        // have a RADIUS username.
        DB::statement(
            'ALTER TABLE hotspot_sessions ALTER COLUMN radius_username DROP NOT NULL'
        );
    }

    public function down(): void
    {
        // Preserve rollback ability if API-backed sessions already exist.
        DB::statement("
            UPDATE hotspot_sessions
            SET radius_username = 'external-' || id::text
            WHERE radius_username IS NULL
        ");

        DB::statement(
            'ALTER TABLE hotspot_sessions ALTER COLUMN radius_username SET NOT NULL'
        );

        Schema::table('hotspot_sessions', function (Blueprint $table) {
            $table->dropIndex('hotspot_sessions_external_lookup');
            $table->dropColumn([
                'source',
                'external_session_id',
                'session_meta',
            ]);
        });
    }
};
