<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCounterAreasTable extends Migration
{
    public function up()
    {
        Schema::create('counter_areas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('number');
            $table->string('description');
            $table->string('identifier');
            $table->string('icon')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
