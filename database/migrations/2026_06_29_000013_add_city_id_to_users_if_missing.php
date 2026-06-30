<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCityIdToUsersIfMissing extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        if (!Schema::hasColumn('users', 'city_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('city_id')->nullable();
                $table->index('city_id', 'users_city_id_idx');
            });
        }

        if (Schema::hasTable('cities') && Schema::hasColumn('users', 'city_id')) {
            Schema::table('users', function (Blueprint $table) {
                try {
                    $table->foreign('city_id', 'users_city_id_fk')->references('id')->on('cities');
                } catch (\Throwable $e) {
                    // Ignore if foreign key already exists or cannot be created in current DB state.
                }
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'city_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropForeign('users_city_id_fk');
            } catch (\Throwable $e) {
                // Ignore if foreign key does not exist.
            }

            try {
                $table->dropIndex('users_city_id_idx');
            } catch (\Throwable $e) {
                // Ignore if index does not exist.
            }

            $table->dropColumn('city_id');
        });
    }
}
