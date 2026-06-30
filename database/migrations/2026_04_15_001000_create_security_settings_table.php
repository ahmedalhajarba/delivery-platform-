<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSecuritySettingsTable extends Migration
{
    public function up()
    {
        Schema::create('security_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('backup_path')->default('backups/database');
            $table->unsignedInteger('backup_retention_days')->default(14);
            $table->string('backup_schedule_time', 5)->default('02:30');
            $table->boolean('backup_compress')->default(true);
            $table->unsignedInteger('backup_timeout_seconds')->default(300);
            $table->string('mysql_dump_binary')->nullable();
            $table->string('pg_dump_binary')->nullable();
            $table->unsignedInteger('audit_retention_days')->default(365);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('security_settings');
    }
}
