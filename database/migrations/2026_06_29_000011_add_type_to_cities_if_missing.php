<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cities') || Schema::hasColumn('cities', 'type')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table) {
            $table->string('type')->default('city')->after('slug');
            $table->index('type');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('cities') || !Schema::hasColumn('cities', 'type')) {
            return;
        }

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
