<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchSectionsTable extends Migration
{
    public function up()
    {
        Schema::create('branch_sections', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title_ar');
            $table->string('title_en');
            $table->longText('description_ar')->nullable();
            $table->longText('description_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
