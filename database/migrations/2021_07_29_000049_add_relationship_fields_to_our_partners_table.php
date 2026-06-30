<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRelationshipFieldsToOurPartnersTable extends Migration
{
    public function up()
    {
        Schema::table('our_partners', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_category_id');
            $table->foreign('partner_category_id', 'partner_category_fk_4368877')->references('id')->on('partners_categories');
        });
    }
}
