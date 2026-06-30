<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('shipment_type');
            $table->string('package_type');
            $table->integer('print_order_count')->default(0);
            $table->longText('package_content')->nullable();
            $table->integer('packages_count')->nullable();
            $table->float('package_weight', 15, 8)->nullable();
            $table->decimal('stated_value', 15, 2)->nullable();
            $table->string('reference_number')->nullable();
            $table->longText('note')->nullable();
            $table->unsignedBigInteger('order_status_id')->default(1);
            $table->string('response')->nullable();
            $table->integer('final_shopping_cost');
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
