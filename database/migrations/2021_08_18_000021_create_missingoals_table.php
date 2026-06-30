<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMissingoalsTable extends Migration
{
    public function up()
    {
        Schema::create('missingoals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->longText('mission');
            $table->longText('mission_ar');
            $table->longText('vision');
            $table->longText('vision_ar');
            $table->longText('goal');
            $table->longText('goal_ar');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
