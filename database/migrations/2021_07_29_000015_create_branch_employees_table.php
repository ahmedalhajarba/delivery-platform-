<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBranchEmployeesTable extends Migration
{
    public function up()
    {
        Schema::create('branch_employees', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('job_title_ar');
            $table->string('job_title_en');
            $table->string('jobid');
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('nationality')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
