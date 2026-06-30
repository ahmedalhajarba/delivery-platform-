<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIntegrationApiLogsTable extends Migration
{
    public function up()
    {
        Schema::create('integration_api_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('request_method', 10);
            $table->string('request_path');
            $table->json('request_payload')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('client_id', 'integration_log_client_fk')->references('id')->on('integration_api_clients')->onDelete('set null');
            $table->foreign('user_id', 'integration_log_user_fk')->references('id')->on('users')->onDelete('set null');
            $table->index(['created_at', 'response_status']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::table('integration_api_logs', function (Blueprint $table) {
            $table->dropForeign('integration_log_client_fk');
            $table->dropForeign('integration_log_user_fk');
        });

        Schema::dropIfExists('integration_api_logs');
    }
}
