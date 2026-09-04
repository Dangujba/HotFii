<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('
            ALTER TABLE nasreload
            ALTER COLUMN nasipaddress TYPE varchar(45)
            USING nasipaddress::text
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE nasreload
            ALTER COLUMN nasipaddress TYPE inet
            USING nasipaddress::inet
        ');
    }
};
