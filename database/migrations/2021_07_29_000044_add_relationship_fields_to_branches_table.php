<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToBranchesTable extends Migration
{
    public function up()
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->unsignedBigInteger('country_id')->nullable();
            $table->foreign('country_id', 'country_fk_4293375')->references('id')->on('countries');
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'user_fk_4293377')->references('id')->on('users');
            $table->unsignedBigInteger('branch_type_id');
            $table->foreign('branch_type_id', 'branch_type_fk_4359806')->references('id')->on('branch_types');
            $table->unsignedBigInteger('branch_category_id');
            $table->foreign('branch_category_id', 'branch_category_fk_4359807')->references('id')->on('branch_categories');
        });
    }
}
