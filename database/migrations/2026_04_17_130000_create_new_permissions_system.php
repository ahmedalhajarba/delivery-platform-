<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // جدول الأقسام (groups/sections)
        Schema::create('permission_sections', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثال: الطلبات، الفروع، المالية...
            $table->string('label')->nullable(); // اسم للعرض
            $table->timestamps();
        });

        // جدول الصلاحيات
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('permission_sections')->onDelete('cascade');
            $table->string('action'); // مثال: access, show, create, edit, delete, print
            $table->string('label')->nullable(); // اسم للعرض
            $table->timestamps();
        });

        // جدول الأدوار
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // admin, manager, ...
            $table->string('label')->nullable();
            $table->timestamps();
        });

        // جدول ربط الأدوار بالصلاحيات
        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');
            $table->timestamps();
        });

        // جدول ربط المستخدمين بالأدوار
        Schema::create('user_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_sections');
    }
};
