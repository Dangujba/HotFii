<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vendor', 24);
            $table->string('model')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('adapter', 80);
            $table->string('support_level', 24)->default('compatible');
            $table->string('status', 24)->default('pending');
            $table->string('nas_identifier')->unique();
            $table->text('radius_secret');
            $table->string('management_address')->nullable();
            $table->text('management_config')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('health')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('certified_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'location_id', 'name']);
        });

        Schema::create('network_device_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_device_id')->constrained()->cascadeOnDelete();
            $table->uuid('run_uuid')->index();
            $table->string('test_key');
            $table->string('status', 24)->default('pending');
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['run_uuid', 'test_key']);
        });

        Schema::create('nas', function (Blueprint $table) {
            $table->id();
            $table->string('nasname', 128)->unique();
            $table->string('shortname', 64)->nullable();
            $table->string('type', 30)->default('other');
            $table->unsignedSmallInteger('ports')->nullable();
            $table->string('secret', 128);
            $table->string('server', 64)->nullable();
            $table->string('community', 64)->nullable();
            $table->string('description', 200)->default('HotFii managed NAS');
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete();
        });

        Schema::create('radcheck', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->index();
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->string('value', 253);
        });

        Schema::create('radreply', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->index();
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->string('value', 253);
        });

        Schema::create('radgroupcheck', function (Blueprint $table) {
            $table->id();
            $table->string('groupname', 64)->index();
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->string('value', 253);
        });

        Schema::create('radgroupreply', function (Blueprint $table) {
            $table->id();
            $table->string('groupname', 64)->index();
            $table->string('attribute', 64);
            $table->string('op', 2)->default(':=');
            $table->string('value', 253);
        });

        Schema::create('radusergroup', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->index();
            $table->string('groupname', 64);
            $table->integer('priority')->default(1);
            $table->index(['username', 'groupname']);
        });

        Schema::create('radacct', function (Blueprint $table) {
            $table->bigIncrements('radacctid');
            $table->string('acctsessionid', 64)->unique();
            $table->string('acctuniqueid', 64)->nullable()->unique();
            $table->string('username', 64)->index();
            $table->string('groupname', 64)->nullable();
            $table->string('realm', 64)->nullable();
            $table->string('nasipaddress', 45)->nullable()->index();
            $table->string('nasportid', 32)->nullable();
            $table->string('nasporttype', 32)->nullable();
            $table->timestampTz('acctstarttime')->nullable()->index();
            $table->timestampTz('acctupdatetime')->nullable();
            $table->timestampTz('acctstoptime')->nullable()->index();
            $table->unsignedBigInteger('acctinterval')->nullable();
            $table->unsignedBigInteger('acctsessiontime')->nullable();
            $table->string('acctauthentic', 32)->nullable();
            $table->string('connectinfo_start', 128)->nullable();
            $table->string('connectinfo_stop', 128)->nullable();
            $table->unsignedBigInteger('acctinputoctets')->nullable();
            $table->unsignedBigInteger('acctoutputoctets')->nullable();
            $table->string('calledstationid', 64)->nullable();
            $table->string('callingstationid', 64)->nullable()->index();
            $table->string('acctterminatecause', 32)->nullable();
            $table->string('servicetype', 32)->nullable();
            $table->string('framedprotocol', 32)->nullable();
            $table->string('framedipaddress', 45)->nullable();
            $table->string('framedipv6address', 45)->nullable();
            $table->string('framedipv6prefix', 64)->nullable();
            $table->string('framedinterfaceid', 64)->nullable();
            $table->string('delegatedipv6prefix', 64)->nullable();
            $table->text('class')->nullable();
        });

        Schema::create('radpostauth', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username', 64);
            $table->string('pass', 64)->nullable();
            $table->string('reply', 32)->nullable();
            $table->timestampTz('authdate')->useCurrent();
            $table->string('class', 64)->nullable();
            $table->index(['username', 'authdate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radpostauth');
        Schema::dropIfExists('radacct');
        Schema::dropIfExists('radusergroup');
        Schema::dropIfExists('radgroupreply');
        Schema::dropIfExists('radgroupcheck');
        Schema::dropIfExists('radreply');
        Schema::dropIfExists('radcheck');
        Schema::dropIfExists('nas');
        Schema::dropIfExists('network_device_tests');
        Schema::dropIfExists('network_devices');
    }
};