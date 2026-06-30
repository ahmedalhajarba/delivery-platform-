<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // حذف الجدول القديم إذا كان موجودًا
        Schema::dropIfExists('permissions');

        // إنشاء الجدول الجديد المنظم
        Schema::create('permissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('section')->nullable()->comment('القسم الرئيسي للصلاحية');
            $table->string('action_key')->nullable()->comment('نوع العملية: access, show, create, edit, delete, print ...');
            $table->string('title')->nullable()->comment('اسم الصلاحية للعرض');
            $table->string('description')->nullable()->comment('وصف إضافي للصلاحية');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('permissions');
    }
};
