<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('strategic_finance')->create('transactions', function (Blueprint $table) {
            // migration removed as per user request
        });
    }

    public function down(): void
    {
        Schema::connection('strategic_finance')->dropIfExists('transactions');
        // migration removed as per user request
        }
    };
