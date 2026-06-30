<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('order_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('insurance_rate');
            $table->integer('divided_number');
            $table->integer('shipping_rate');
            $table->integer('over_weight_cost');
            $table->integer('print_copies');
            $table->integer('allowed_weight')->nullable();
            $table->longText('print_settings')->nullable();
            $table->string('sender');
            $table->string('recipient');
            $table->string('shipment_type');
            $table->string('package_content');
            $table->string('packages_count');
            $table->string('package_weight');
            $table->string('actual_weight');
            $table->string('length');
            $table->string('width');
            $table->string('height');
            $table->string('stated_value');
            $table->string('reference_number');
            $table->string('note');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
