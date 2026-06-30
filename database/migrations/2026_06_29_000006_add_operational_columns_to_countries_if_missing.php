<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('countries')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table) {
            if (!Schema::hasColumn('countries', 'iso3')) {
                $table->string('iso3', 3)->nullable()->after('short_code');
            }

            if (!Schema::hasColumn('countries', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('iso3');
            }

            if (!Schema::hasColumn('countries', 'responsible_user_id')) {
                $table->unsignedBigInteger('responsible_user_id')->nullable()->after('branch_id');
            }

            if (!Schema::hasColumn('countries', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('responsible_user_id');
            }

            if (!Schema::hasColumn('countries', 'allow_pickup')) {
                $table->boolean('allow_pickup')->default(true)->after('is_active');
            }

            if (!Schema::hasColumn('countries', 'allow_delivery')) {
                $table->boolean('allow_delivery')->default(true)->after('allow_pickup');
            }

            if (!Schema::hasColumn('countries', 'allow_subscriptions')) {
                $table->boolean('allow_subscriptions')->default(true)->after('allow_delivery');
            }

            if (!Schema::hasColumn('countries', 'allow_extra_services')) {
                $table->boolean('allow_extra_services')->default(true)->after('allow_subscriptions');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('countries')) {
            return;
        }

        Schema::table('countries', function (Blueprint $table) {
            if (Schema::hasColumn('countries', 'allow_extra_services')) {
                $table->dropColumn('allow_extra_services');
            }

            if (Schema::hasColumn('countries', 'allow_subscriptions')) {
                $table->dropColumn('allow_subscriptions');
            }

            if (Schema::hasColumn('countries', 'allow_delivery')) {
                $table->dropColumn('allow_delivery');
            }

            if (Schema::hasColumn('countries', 'allow_pickup')) {
                $table->dropColumn('allow_pickup');
            }

            if (Schema::hasColumn('countries', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('countries', 'responsible_user_id')) {
                $table->dropColumn('responsible_user_id');
            }

            if (Schema::hasColumn('countries', 'branch_id')) {
                $table->dropColumn('branch_id');
            }

            if (Schema::hasColumn('countries', 'iso3')) {
                $table->dropColumn('iso3');
            }
        });
    }
};
