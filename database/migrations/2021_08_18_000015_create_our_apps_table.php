<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOurAppsTable extends Migration
{
    public function up()
    {
        Schema::create('our_apps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_en');
            $table->string('title_ar');
            $table->string('description_en');
            $table->string('description_ar');
            $table->string('android_store_link')->nullable();
            $table->string('apple_store_link')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
