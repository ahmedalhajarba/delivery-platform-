<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::connection('strategic_finance')->create('bank_statement', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 18, 2)->nullable();
            $table->decimal('debit', 18, 2)->nullable();
            $table->decimal('credit', 18, 2)->nullable();
            $table->string('operation_details')->nullable();
            $table->string('beneficiary_name')->nullable();
            $table->date('operation_date')->nullable();
            $table->string('raw_balance', 50)->nullable();
            $table->string('raw_debit', 50)->nullable();
            $table->string('raw_credit', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('strategic_finance')->dropIfExists('bank_statement');
    }
};