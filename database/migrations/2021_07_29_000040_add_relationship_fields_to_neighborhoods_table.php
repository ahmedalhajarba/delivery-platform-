<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToNeighborhoodsTable extends Migration
{
    public function up()
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id', 'city_fk_4488993')->references('id')->on('cities');
        });
    }
}
