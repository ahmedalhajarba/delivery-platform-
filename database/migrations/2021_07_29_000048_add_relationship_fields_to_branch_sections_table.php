<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToBranchSectionsTable extends Migration
{
    public function up()
    {
        Schema::table('branch_sections', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id', 'user_fk_4359842')->references('id')->on('users');
        });
    }
}
