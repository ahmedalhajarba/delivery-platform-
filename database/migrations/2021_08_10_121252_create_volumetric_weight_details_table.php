<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVolumetricWeightDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('volumetric_weight_details', function (Blueprint $table) {
            $table->id();

            $table->integer('pieces')->nullable();
            $table->float('length', 15, 2)->nullable();
            $table->float('width', 15, 2)->nullable();
            $table->float('height', 15, 2)->nullable();
            $table->float('actual_weight')->nullable();
            $table->float('approved_weight')->nullable();
            $table->float('fee_weight')->nullable();
            $table->float('unit_declared_value')->nullable();
            $table->unsignedInteger('order_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('volumetric_weight_details');
    }
}
