<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToInsurancesTable extends Migration
{
    public function up()
    {
        Schema::table('insurances', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id', 'order_fk_4463567')->references('id')->on('orders');
        });
    }
}
