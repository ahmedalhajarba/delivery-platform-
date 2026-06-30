<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHowDoWorksTable extends Migration
{
    public function up()
    {
        Schema::create('how_do_works', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_first_column');
            $table->longText('des_first_column');
            $table->string('title_second_column');
            $table->string('des_second_column');
            $table->string('title_third_column');
            $table->longText('des_third_column');
            $table->string('title_four_column');
            $table->string('des_four_column');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
