<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToRegionsTable extends Migration
{
    public function up()
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id');
            $table->foreign('country_id', 'country_fk_4488862')->references('id')->on('countries');
        });
    }
}
