<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToCompaniesTable extends Migration
{
    public function up()
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id', 'city_fk_4274634')->references('id')->on('cities');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'user_fk_4275582')->references('id')->on('users');
            $table->unsignedBigInteger('company_type_id');
            $table->foreign('company_type_id', 'company_type_fk_4275592')->references('id')->on('company_types');
        });
    }
}
