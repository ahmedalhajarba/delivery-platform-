<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLegalResponsibilityPagesTable extends Migration
{
    public function up()
    {
        Schema::create('legal_responsibility_pages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en');
            $table->longText('text_ar')->nullable();
            $table->longText('text_en')->nullable();
            $table->longText('paragraph_ar')->nullable();
            $table->longText('paragraph_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
