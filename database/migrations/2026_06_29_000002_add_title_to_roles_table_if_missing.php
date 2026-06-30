<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            return;
        }

        if (!Schema::hasColumn('roles', 'title')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('title')->nullable()->after('id');
            });
        }

        DB::table('roles')
            ->whereNull('title')
            ->update([
                'title' => DB::raw("COALESCE(NULLIF(label, ''), NULLIF(name, ''), NULLIF(slug, ''))"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'title')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('title');
            });
        }
    }
};
