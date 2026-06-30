<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAboutSitesTable extends Migration
{
    public function up()
    {
        Schema::create('about_sites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('title_ar');
            $table->longText('breif');
            $table->string('breif_ar');
            $table->longText('description');
            $table->longText('description_ar');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
