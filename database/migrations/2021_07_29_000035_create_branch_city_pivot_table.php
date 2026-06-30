<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchCityPivotTable extends Migration
{
    public function up()
    {
        Schema::create('branch_city', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id', 'branch_id_fk_4293376')->references('id')->on('branches')->onDelete('cascade');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id', 'city_id_fk_4293376')->references('id')->on('cities')->onDelete('cascade');
        });
    }
}
