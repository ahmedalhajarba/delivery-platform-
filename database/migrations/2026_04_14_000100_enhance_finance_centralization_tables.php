<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhanceFinanceCentralizationTables extends Migration
{
    public function up()
    {
        Schema::table('cash_boxes', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_boxes', 'channel')) {
                $table->string('channel', 30)
                    ->default('cash_box')
                    ->after('type')
                    ->comment('cash_box|bank_account|payment_gateway|employee_custody');
            }

            if (!Schema::hasColumn('cash_boxes', 'manager_user_id')) {
                $table->unsignedBigInteger('manager_user_id')->nullable()->after('courier_id');
                $table->foreign('manager_user_id')->references('id')->on('users')->onDelete('set null');
            }

            if (!Schema::hasColumn('cash_boxes', 'account_name')) {
                $table->string('account_name')->nullable()->after('name_en');
            }

            if (!Schema::hasColumn('cash_boxes', 'account_number')) {
                $table->string('account_number')->nullable()->after('account_name');
            }

            if (!Schema::hasColumn('cash_boxes', 'iban')) {
                $table->string('iban')->nullable()->after('account_number');
            }

            if (!Schema::hasColumn('cash_boxes', 'gateway_name')) {
                $table->string('gateway_name')->nullable()->after('iban');
            }
        });

        // تم تعطيل جميع عمليات التعديل على جدول receipts بعد حذف قاعدة بيانات strategic_finance

        Schema::table('service_purchases', function (Blueprint $table) {
            if (!Schema::hasColumn('service_purchases', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('set null');
            }

            // تم تعطيل إضافة receipt_id وربطه بجدول receipts نهائيًا بعد حذف قاعدة بيانات strategic_finance

            if (!Schema::hasColumn('service_purchases', 'cash_box_id')) {
                $table->unsignedBigInteger('cash_box_id')->nullable()->after('receipt_id');
                $table->foreign('cash_box_id')->references('id')->on('cash_boxes')->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('service_purchases', function (Blueprint $table) {
            if (Schema::hasColumn('service_purchases', 'cash_box_id')) {
                $table->dropForeign(['cash_box_id']);
                $table->dropColumn('cash_box_id');
            }

            // تم تعطيل حذف receipt_id نهائيًا بعد حذف قاعدة بيانات strategic_finance

            if (Schema::hasColumn('service_purchases', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropColumn('invoice_id');
            }
        });

        // تم تعطيل جميع عمليات التعديل على جدول receipts في دالة down بعد حذف قاعدة بيانات strategic_finance

        Schema::table('cash_boxes', function (Blueprint $table) {
            if (Schema::hasColumn('cash_boxes', 'manager_user_id')) {
                $table->dropForeign(['manager_user_id']);
            }

            $columns = array_filter([
                Schema::hasColumn('cash_boxes', 'channel') ? 'channel' : null,
                Schema::hasColumn('cash_boxes', 'manager_user_id') ? 'manager_user_id' : null,
                Schema::hasColumn('cash_boxes', 'account_name') ? 'account_name' : null,
                Schema::hasColumn('cash_boxes', 'account_number') ? 'account_number' : null,
                Schema::hasColumn('cash_boxes', 'iban') ? 'iban' : null,
                Schema::hasColumn('cash_boxes', 'gateway_name') ? 'gateway_name' : null,
            ]);

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
