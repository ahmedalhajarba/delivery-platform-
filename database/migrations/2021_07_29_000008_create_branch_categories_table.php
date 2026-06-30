<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('branch_categories', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
