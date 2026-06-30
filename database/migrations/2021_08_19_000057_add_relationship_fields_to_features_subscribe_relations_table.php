<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToFeaturesSubscribeRelationsTable extends Migration
{
    public function up()
    {
        Schema::table('features_subscribe_relations', function (Blueprint $table) {
            $table->unsignedBigInteger('feature_id')->nullable();
            $table->foreign('feature_id', 'feature_fk_4678696')->references('id')->on('features_subscribes');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->foreign('subscription_id', 'subscription_fk_4678697')->references('id')->on('subscriptions_plans');
        });
    }
}
