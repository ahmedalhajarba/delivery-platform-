<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cities') || Schema::hasColumn('cities', 'country_id')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('governorate_id');
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cities') || !Schema::hasColumn('cities', 'country_id')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
