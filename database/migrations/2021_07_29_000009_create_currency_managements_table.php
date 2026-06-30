<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCurrencyManagementsTable extends Migration
{
    public function up()
    {
        Schema::create('currency_managements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en');
            $table->string('symbol');
            $table->string('status');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
