<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddInsurancePolicyAndCodFields extends Migration
{
    public function up()
    {
        Schema::table('insurances', function (Blueprint $table) {
            if (!Schema::hasColumn('insurances', 'policy_number')) {
                $table->string('policy_number')->nullable()->after('id');
            }

            if (!Schema::hasColumn('insurances', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('order_id');
                $table->foreign('invoice_id', 'insurance_invoice_fk')->references('id')->on('invoices')->onDelete('set null');
            }

            if (!Schema::hasColumn('insurances', 'status')) {
                $table->string('status', 30)->default('issued')->after('invoice_id');
            }

            if (!Schema::hasColumn('insurances', 'issued_at')) {
                $table->date('issued_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('insurances', 'start_date')) {
                $table->date('start_date')->nullable()->after('issued_at');
            }

            if (!Schema::hasColumn('insurances', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }

            if (!Schema::hasColumn('insurances', 'terms_and_conditions')) {
                $table->longText('terms_and_conditions')->nullable()->after('note');
            }

        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('recipient_id');
                $table->foreign('invoice_id', 'orders_invoice_fk')->references('id')->on('invoices')->onDelete('set null');
            }

            if (!Schema::hasColumn('orders', 'cod_amount')) {
                $table->decimal('cod_amount', 15, 2)->default(0)->after('stated_value');
            }

            if (!Schema::hasColumn('orders', 'bank_fee_amount')) {
                $table->decimal('bank_fee_amount', 15, 2)->default(0)->after('cod_amount');
            }

            if (!Schema::hasColumn('orders', 'clearing_amount')) {
                $table->decimal('clearing_amount', 15, 2)->default(0)->after('bank_fee_amount');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'invoice_id')) {
                $table->dropForeign('orders_invoice_fk');
            }

            $dropColumns = array_filter([
                Schema::hasColumn('orders', 'invoice_id') ? 'invoice_id' : null,
                Schema::hasColumn('orders', 'cod_amount') ? 'cod_amount' : null,
                Schema::hasColumn('orders', 'bank_fee_amount') ? 'bank_fee_amount' : null,
                Schema::hasColumn('orders', 'clearing_amount') ? 'clearing_amount' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });

        Schema::table('insurances', function (Blueprint $table) {
            if (Schema::hasColumn('insurances', 'invoice_id')) {
                $table->dropForeign('insurance_invoice_fk');
            }

            $dropColumns = array_filter([
                Schema::hasColumn('insurances', 'policy_number') ? 'policy_number' : null,
                Schema::hasColumn('insurances', 'invoice_id') ? 'invoice_id' : null,
                Schema::hasColumn('insurances', 'status') ? 'status' : null,
                Schema::hasColumn('insurances', 'issued_at') ? 'issued_at' : null,
                Schema::hasColumn('insurances', 'start_date') ? 'start_date' : null,
                Schema::hasColumn('insurances', 'end_date') ? 'end_date' : null,
                Schema::hasColumn('insurances', 'terms_and_conditions') ? 'terms_and_conditions' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
