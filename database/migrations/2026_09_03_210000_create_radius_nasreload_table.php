<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            CREATE TABLE IF NOT EXISTS nasreload (
                nasipaddress inet PRIMARY KEY,
                reloadtime timestamptz NOT NULL
            )
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('nasreload');
    }
};
