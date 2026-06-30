<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserSubscriptionsTableIfMissing extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('user_subscriptions')) {
            Schema::create('user_subscriptions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('subscription_id')->nullable();
                $table->decimal('monthly_price', 15, 2)->nullable();
                $table->decimal('discount', 8, 2)->nullable();
                $table->integer('order_limit')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->tinyInteger('status')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index('user_id', 'user_subscriptions_user_id_idx');
                $table->index('subscription_id', 'user_subscriptions_subscription_id_idx');
                $table->index('status', 'user_subscriptions_status_idx');
                $table->index('end_date', 'user_subscriptions_end_date_idx');
            });
        }

        Schema::table('user_subscriptions', function (Blueprint $table) {
            if (Schema::hasTable('users')) {
                try {
                    $table->foreign('user_id', 'user_subscriptions_user_fk')->references('id')->on('users')->onDelete('set null');
                } catch (\Throwable $e) {
                    // Ignore if FK already exists or cannot be created in current DB state.
                }
            }

            if (Schema::hasTable('subscriptions_plans')) {
                try {
                    $table->foreign('subscription_id', 'user_subscriptions_subscription_fk')->references('id')->on('subscriptions_plans')->onDelete('set null');
                } catch (\Throwable $e) {
                    // Ignore if FK already exists or cannot be created in current DB state.
                }
            }
        });
    }

    public function down()
    {
        if (!Schema::hasTable('user_subscriptions')) {
            return;
        }

        Schema::table('user_subscriptions', function (Blueprint $table) {
            try {
                $table->dropForeign('user_subscriptions_user_fk');
            } catch (\Throwable $e) {
                // Ignore missing foreign key.
            }

            try {
                $table->dropForeign('user_subscriptions_subscription_fk');
            } catch (\Throwable $e) {
                // Ignore missing foreign key.
            }
        });

        Schema::dropIfExists('user_subscriptions');
    }
}
