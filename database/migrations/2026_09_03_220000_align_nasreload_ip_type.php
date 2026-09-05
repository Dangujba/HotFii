<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ALTER COLUMN ... USING is PostgreSQL grammar, and inet is a PostgreSQL
        // type. The test suite runs on SQLite, which has no inet to align away
        // from and would abort the whole migration run here — taking every
        // feature test with it. Production is PostgreSQL; this is a no-op
        // everywhere else.
        if (! $this->onPostgres()) {
            return;
        }

        DB::statement('
            ALTER TABLE nasreload
            ALTER COLUMN nasipaddress TYPE varchar(45)
            USING nasipaddress::text
        ');
    }

    public function down(): void
    {
        if (! $this->onPostgres()) {
            return;
        }

        DB::statement('
            ALTER TABLE nasreload
            ALTER COLUMN nasipaddress TYPE inet
            USING nasipaddress::inet
        ');
    }

    private function onPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
};
