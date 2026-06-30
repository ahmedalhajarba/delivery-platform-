<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::dropIfExists('permissions');
    }
    public function down()
    {
        // لا حاجة لإرجاع الجدول القديم
    }
};
