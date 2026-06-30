<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('permission_sections', function (Blueprint $table) {
            if (!Schema::hasColumn('permission_sections', 'domain')) {
                $table->string('domain')->nullable()->after('label')->index();
            }
        });
    }

    public function down()
    {
        Schema::table('permission_sections', function (Blueprint $table) {
            if (Schema::hasColumn('permission_sections', 'domain')) {
                $table->dropColumn('domain');
            }
        });
    }
};
