<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * FreeRADIUS releases published after the BlastRADIUS advisory added
     * require_message_authenticator and limit_proxy_state to the stock
     * PostgreSQL nas schema, and their client_query selects both columns.
     * Without them read_clients fails and no router can authenticate.
     * Older releases simply ignore the extra columns.
     */
    public function up(): void
    {
        Schema::table('nas', function (Blueprint $table) {
            $table->string('require_message_authenticator', 4)->default('no');
            $table->string('limit_proxy_state', 4)->default('auto');
        });
    }

    public function down(): void
    {
        Schema::table('nas', function (Blueprint $table) {
            $table->dropColumn(['require_message_authenticator', 'limit_proxy_state']);
        });
    }
};
