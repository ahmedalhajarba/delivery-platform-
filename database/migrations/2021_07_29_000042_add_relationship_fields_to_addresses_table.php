<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToAddressesTable extends Migration
{
    public function up()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'user_fk_4374706')->references('id')->on('users');
            $table->unsignedBigInteger('country_id');
            $table->foreign('country_id', 'country_fk_4374709')->references('id')->on('countries');
            $table->unsignedBigInteger('governorate_id');
            $table->foreign('governorate_id', 'governorate_fk_4374710')->references('id')->on('governorates');
            $table->unsignedBigInteger('city_id');
            $table->foreign('city_id', 'city_fk_4374711')->references('id')->on('cities');
            $table->unsignedBigInteger('neighborhood_id');
            $table->foreign('neighborhood_id', 'neighborhood_fk_4374712')->references('id')->on('villages');
        });
    }
}
