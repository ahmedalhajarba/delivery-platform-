<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_types')) {
            return;
        }

        Schema::table('user_types', function (Blueprint $table) {
            if (!Schema::hasColumn('user_types', 'icon')) {
                $table->string('icon')->nullable()->after('title_en');
            }

            if (!Schema::hasColumn('user_types', 'color')) {
                $table->string('color', 20)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('user_types')) {
            return;
        }

        Schema::table('user_types', function (Blueprint $table) {
            if (Schema::hasColumn('user_types', 'color')) {
                $table->dropColumn('color');
            }

            if (Schema::hasColumn('user_types', 'icon')) {
                $table->dropColumn('icon');
            }
        });
    }
};
