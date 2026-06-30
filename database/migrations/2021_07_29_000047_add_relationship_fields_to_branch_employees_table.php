<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToBranchEmployeesTable extends Migration
{
    public function up()
    {
        Schema::table('branch_employees', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id');
            $table->foreign('branch_id', 'branch_fk_4360049')->references('id')->on('branches');
            $table->unsignedBigInteger('country_id');
            $table->foreign('country_id', 'country_fk_4360054')->references('id')->on('countries');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id', 'city_fk_4360055')->references('id')->on('cities');
        });
    }
}
