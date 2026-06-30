<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePayrollAndFinanceDocumentsTables extends Migration
{
    public function up()
    {
        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->enum('calculation_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('percentage', 5, 2)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('employee_payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('payroll_month');
            $table->decimal('basic_salary', 12, 2)->default(0);
            $table->decimal('insurance_amount', 12, 2)->default(0);
            $table->decimal('allowances_amount', 12, 2)->default(0);
            $table->decimal('bonuses_amount', 12, 2)->default(0);
            $table->decimal('incentives_amount', 12, 2)->default(0);
            $table->decimal('scheduled_deductions_amount', 12, 2)->default(0);
            $table->decimal('manual_deductions_amount', 12, 2)->default(0);
            $table->decimal('total_deductions_amount', 12, 2)->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'payroll_month']);
            $table->index(['status', 'payroll_month']);
        });

        Schema::create('finance_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_number')->unique();
            $table->enum('document_type', ['expense_invoice', 'purchase_invoice', 'payment_voucher']);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('related_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('related_employee_id')->nullable()->constrained('branch_employees')->nullOnDelete();
            $table->string('beneficiary_name')->nullable();
            $table->string('title');
            $table->date('document_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['draft', 'submitted', 'approved', 'settled', 'rejected'])->default('draft');
            $table->enum('clearance_status', ['none', 'pending', 'partial', 'cleared'])->default('pending');
            $table->string('attachment_path')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['document_type', 'status']);
            $table->index(['document_date', 'branch_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('finance_documents');
        Schema::dropIfExists('employee_payrolls');
        Schema::dropIfExists('employee_deductions');
    }
}
