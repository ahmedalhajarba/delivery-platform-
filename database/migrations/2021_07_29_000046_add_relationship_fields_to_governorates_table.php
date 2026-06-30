<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToGovernoratesTable extends Migration
{
    public function up()
    {
        Schema::table('governorates', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id');
            $table->foreign('region_id', 'region_fk_4488959')->references('id')->on('regions');
        });
    }
}
