<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permission_user')) {
            return;
        }

        Schema::create('permission_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->unique(['user_id', 'permission_id'], 'permission_user_unique_pair');

            $table->foreign('user_id', 'user_id_fk_permission_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('permission_id', 'permission_id_fk_permission_user')
                ->references('id')
                ->on('permissions')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('permission_user')) {
            Schema::drop('permission_user');
        }
    }
};
