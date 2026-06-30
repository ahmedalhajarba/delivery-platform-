<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('governorates') || Schema::hasColumn('governorates', 'country_id')) {
            return;
        }

        Schema::table('governorates', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable()->after('region_id');
            $table->index('country_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('governorates') || !Schema::hasColumn('governorates', 'country_id')) {
            return;
        }

        Schema::table('governorates', function (Blueprint $table) {
            $table->dropIndex(['country_id']);
            $table->dropColumn('country_id');
        });
    }
};
