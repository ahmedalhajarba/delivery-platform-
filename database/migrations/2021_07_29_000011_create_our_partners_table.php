<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOurPartnersTable extends Migration
{
    public function up()
    {
        Schema::create('our_partners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('partner_type');
            $table->longText('description_ar')->nullable();
            $table->longText('description_en')->nullable();
            $table->string('link')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
