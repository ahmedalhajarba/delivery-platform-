<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('trade_name_ar')->nullable();
            $table->string('trade_name_en')->nullable();
            $table->string('have_en_account');
            $table->string('country')->nullable();
            $table->string('account_code')->nullable();
            $table->string('account_number')->nullable();
            $table->string('crn');
            $table->string('tax');
            $table->string('tax_exemption')->nullable();
            $table->string('street_name')->nullable();
            $table->string('mobile')->nullable();
            $table->longText('finde_us')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
