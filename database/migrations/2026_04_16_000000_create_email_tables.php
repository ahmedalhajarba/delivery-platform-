<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('strategic_finance')->create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('user_email')->unique();
            $table->string('provider')->default('gmail');
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::connection('strategic_finance')->create('email_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained('email_accounts')->onDelete('cascade');
            $table->string('label_id');
            $table->string('name');
            $table->timestamps();
        });

        Schema::connection('strategic_finance')->create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained('email_accounts')->onDelete('cascade');
            $table->string('email_id');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->json('labels')->nullable();
            $table->timestamps();
        });

        Schema::connection('strategic_finance')->create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained('emails')->onDelete('cascade');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->string('path');
            $table->integer('size')->nullable();
            $table->timestamps();
        });
        }
        // migration removed as per user request
    
        // public function down(): void
        // {
        //     Schema::dropIfExists('email_attachments');
        //     Schema::dropIfExists('emails');
        //     Schema::dropIfExists('email_labels');
        //     Schema::dropIfExists('email_accounts');
        // }
};
