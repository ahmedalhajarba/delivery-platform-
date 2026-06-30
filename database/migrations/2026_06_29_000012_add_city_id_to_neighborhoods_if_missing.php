<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCityIdToNeighborhoodsIfMissing extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('neighborhoods')) {
            return;
        }

        if (!Schema::hasColumn('neighborhoods', 'city_id')) {
            Schema::table('neighborhoods', function (Blueprint $table) {
                // Nullable avoids failing on existing rows in partially migrated databases.
                $table->unsignedBigInteger('city_id')->nullable()->after('id');
                $table->index('city_id', 'neighborhoods_city_id_idx');
            });
        }

        if (Schema::hasTable('cities') && Schema::hasColumn('neighborhoods', 'city_id')) {
            Schema::table('neighborhoods', function (Blueprint $table) {
                try {
                    $table->foreign('city_id', 'neighborhoods_city_id_fk')->references('id')->on('cities');
                } catch (\Throwable $e) {
                    // Ignore if the foreign key already exists or cannot be created in current DB state.
                }
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('neighborhoods') || !Schema::hasColumn('neighborhoods', 'city_id')) {
            return;
        }

        Schema::table('neighborhoods', function (Blueprint $table) {
            try {
                $table->dropForeign('neighborhoods_city_id_fk');
            } catch (\Throwable $e) {
                // Ignore if the foreign key does not exist.
            }

            try {
                $table->dropIndex('neighborhoods_city_id_idx');
            } catch (\Throwable $e) {
                // Ignore if the index does not exist.
            }

            $table->dropColumn('city_id');
        });
    }
}
