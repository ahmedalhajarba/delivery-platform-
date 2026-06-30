<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->foreign('sender_id', 'sender_fk_4374818')->references('id')->on('addresses');
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->foreign('recipient_id', 'recipient_fk_4374819')->references('id')->on('addresses');
        });
    }
}
