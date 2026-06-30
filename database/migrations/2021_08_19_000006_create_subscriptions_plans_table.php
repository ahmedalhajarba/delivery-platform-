<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSubscriptionsPlansTable extends Migration
{
    public function up()
    {
        Schema::create('subscriptions_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->decimal('m_price', 15, 2);
            $table->integer('subscription_period');
            $table->longText('description_ar')->nullable();
            $table->longText('description_en')->nullable();
            $table->string('status');
            $table->string('store_type');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
