<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_sections');
        Schema::dropIfExists('permission_role');
        // أضف أي جداول pivot أو صلاحيات أخرى قديمة هنا إذا لزم الأمر
        Schema::enableForeignKeyConstraints();
    }
    public function down()
    {
        // لا حاجة لإرجاع الجداول
    }
};
