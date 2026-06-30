<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_user_type')) {
            return;
        }

        Schema::create('role_user_type', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_type_id');

            $table->index('role_id');
            $table->index('user_type_id');
            $table->primary(['role_id', 'user_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user_type');
    }
};
