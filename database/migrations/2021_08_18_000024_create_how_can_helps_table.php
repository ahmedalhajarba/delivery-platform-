<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHowCanHelpsTable extends Migration
{
    public function up()
    {
        Schema::create('how_can_helps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('icon')->nullable();
            $table->string('title_en');
            $table->string('title_ar');
            $table->string('description_en');
            $table->string('description_ar');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
