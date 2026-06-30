<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiteSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en')->nullable();
            $table->longText('site_footer');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('mobile');
            $table->string('mobile_b')->nullable();
            $table->string('mobile_c')->nullable();
            $table->string('ios_url')->nullable();
            $table->string('android_url')->nullable();
            $table->string('harmony_url')->nullable();
            $table->longText('description_ar')->nullable();
            $table->longText('description_en')->nullable();
            $table->longText('key_words_ar')->nullable();
            $table->longText('key_words_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
