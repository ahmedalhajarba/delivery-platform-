<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTermsConditionsTable extends Migration
{
    public function up()
    {
        Schema::create('terms_conditions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en');
            $table->longText('text_ar');
            $table->longText('text_en');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
