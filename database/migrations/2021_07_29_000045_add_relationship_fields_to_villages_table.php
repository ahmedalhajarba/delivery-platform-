<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToVillagesTable extends Migration
{
    public function up()
    {
        Schema::table('villages', function (Blueprint $table) {
            $table->unsignedBigInteger('governorate_id');
            $table->foreign('governorate_id', 'governorate_fk_4488966')->references('id')->on('governorates');
        });
    }
}
