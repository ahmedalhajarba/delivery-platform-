<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationApiClientsTable extends Migration
{
    public function up()
    {
        Schema::create('integration_api_clients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('key_hash', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->json('allowed_scopes')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'integration_client_user_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('created_by', 'integration_client_created_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->foreign('updated_by', 'integration_client_updated_by_fk')->references('id')->on('users')->onDelete('set null');
            $table->index(['status', 'user_id']);
        });
    }

    public function down()
    {
        Schema::table('integration_api_clients', function (Blueprint $table) {
            $table->dropForeign('integration_client_user_fk');
            $table->dropForeign('integration_client_created_by_fk');
            $table->dropForeign('integration_client_updated_by_fk');
        });

        Schema::dropIfExists('integration_api_clients');
    }
}
