<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCourierFinanceTables extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('courier_trip_financials')) {
            Schema::create('courier_trip_financials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_employee_id')->constrained('branch_employees')->cascadeOnDelete();
                $table->string('trip_code')->nullable();
                $table->date('trip_date');
                $table->decimal('base_wage', 12, 2)->default(0);
                $table->decimal('commission_amount', 12, 2)->default(0);
                $table->decimal('bonus_amount', 12, 2)->default(0);
                $table->decimal('deduction_amount', 12, 2)->default(0);
                $table->decimal('operational_cost', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['branch_employee_id', 'trip_date']);
                $table->index(['status', 'trip_date']);
            });
        }

        if (!Schema::hasTable('courier_settlements')) {
            Schema::create('courier_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('settlement_number')->unique();
                $table->foreignId('branch_employee_id')->constrained('branch_employees')->cascadeOnDelete();
                $table->date('settlement_date');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->decimal('total_base_amount', 12, 2)->default(0);
                $table->decimal('total_commission_amount', 12, 2)->default(0);
                $table->decimal('total_bonus_amount', 12, 2)->default(0);
                $table->decimal('total_deduction_amount', 12, 2)->default(0);
                $table->decimal('total_operational_cost', 12, 2)->default(0);
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->decimal('balance_amount', 12, 2)->default(0);
                $table->enum('status', ['draft', 'approved', 'partial', 'paid', 'cancelled'])->default('draft');
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['branch_employee_id', 'settlement_date']);
                $table->index(['status', 'settlement_date']);
            });
        }

        if (!Schema::hasTable('courier_settlement_items')) {
            Schema::create('courier_settlement_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('courier_settlement_id')->constrained('courier_settlements')->cascadeOnDelete();
                $table->foreignId('courier_trip_financial_id')->constrained('courier_trip_financials')->cascadeOnDelete();
                $table->decimal('net_amount', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['courier_settlement_id', 'courier_trip_financial_id'], 'courier_settlement_items_unique');
                $table->index(['courier_trip_financial_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('courier_settlement_items');
        Schema::dropIfExists('courier_settlements');
        Schema::dropIfExists('courier_trip_financials');
    }
}
