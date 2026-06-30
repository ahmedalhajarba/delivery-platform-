<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipColumnsToBranchEmployeesIfMissing extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('branch_employees')) {
            return;
        }

        Schema::table('branch_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('branch_employees', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->index('branch_id', 'branch_employees_branch_id_idx');
            }

            if (!Schema::hasColumn('branch_employees', 'country_id')) {
                $table->unsignedBigInteger('country_id')->nullable();
                $table->index('country_id', 'branch_employees_country_id_idx');
            }

            if (!Schema::hasColumn('branch_employees', 'city_id')) {
                $table->unsignedBigInteger('city_id')->nullable();
                $table->index('city_id', 'branch_employees_city_id_idx');
            }
        });

        Schema::table('branch_employees', function (Blueprint $table) {
            if (Schema::hasTable('branches') && Schema::hasColumn('branch_employees', 'branch_id')) {
                try {
                    $table->foreign('branch_id', 'branch_employees_branch_fk')->references('id')->on('branches');
                } catch (\Throwable $e) {
                    // Ignore if foreign key already exists or cannot be created in current DB state.
                }
            }

            if (Schema::hasTable('countries') && Schema::hasColumn('branch_employees', 'country_id')) {
                try {
                    $table->foreign('country_id', 'branch_employees_country_fk')->references('id')->on('countries');
                } catch (\Throwable $e) {
                    // Ignore if foreign key already exists or cannot be created in current DB state.
                }
            }

            if (Schema::hasTable('cities') && Schema::hasColumn('branch_employees', 'city_id')) {
                try {
                    $table->foreign('city_id', 'branch_employees_city_fk')->references('id')->on('cities');
                } catch (\Throwable $e) {
                    // Ignore if foreign key already exists or cannot be created in current DB state.
                }
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('branch_employees')) {
            return;
        }

        Schema::table('branch_employees', function (Blueprint $table) {
            try {
                $table->dropForeign('branch_employees_branch_fk');
            } catch (\Throwable $e) {
                // Ignore if foreign key does not exist.
            }

            try {
                $table->dropForeign('branch_employees_country_fk');
            } catch (\Throwable $e) {
                // Ignore if foreign key does not exist.
            }

            try {
                $table->dropForeign('branch_employees_city_fk');
            } catch (\Throwable $e) {
                // Ignore if foreign key does not exist.
            }

            try {
                $table->dropIndex('branch_employees_branch_id_idx');
            } catch (\Throwable $e) {
                // Ignore if index does not exist.
            }

            try {
                $table->dropIndex('branch_employees_country_id_idx');
            } catch (\Throwable $e) {
                // Ignore if index does not exist.
            }

            try {
                $table->dropIndex('branch_employees_city_id_idx');
            } catch (\Throwable $e) {
                // Ignore if index does not exist.
            }

            if (Schema::hasColumn('branch_employees', 'branch_id')) {
                $table->dropColumn('branch_id');
            }

            if (Schema::hasColumn('branch_employees', 'country_id')) {
                $table->dropColumn('country_id');
            }

            if (Schema::hasColumn('branch_employees', 'city_id')) {
                $table->dropColumn('city_id');
            }
        });
    }
}
