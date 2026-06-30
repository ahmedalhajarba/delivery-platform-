<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'order_count')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('order_count')->default(0)->after('remember_token');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'order_count')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('order_count');
            });
        }
    }
};
