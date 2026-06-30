<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFinancialOfficersTable extends Migration
{
    public function up()
    {
        Schema::create('financial_officers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email');
            $table->string('mobile');
            $table->string('co_address')->nullable();
            $table->longText('street')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
