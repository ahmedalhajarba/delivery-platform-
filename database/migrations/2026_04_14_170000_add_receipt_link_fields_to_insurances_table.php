<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReceiptLinkFieldsToInsurancesTable extends Migration
{
    public function up()
    {
        Schema::table('insurances', function (Blueprint $table) {
            if (!Schema::hasColumn('insurances', 'receipt_id')) {
                $table->unsignedBigInteger('receipt_id')->nullable()->after('invoice_id');
                if (Schema::hasTable('receipts')) {
                    $table->foreign('receipt_id', 'insurance_receipt_fk')
                        ->references('id')
                        ->on('receipts')
                        ->onDelete('set null');
                }
            }

            if (!Schema::hasColumn('insurances', 'original_receipt_number')) {
                $table->string('original_receipt_number', 100)->nullable()->after('receipt_id');
            }
        });
    }

    public function down()
    {
        Schema::table('insurances', function (Blueprint $table) {
            if (Schema::hasColumn('insurances', 'receipt_id')) {
                try {
                    $table->dropForeign('insurance_receipt_fk');
                } catch (\Throwable $e) {
                    // Ignore when FK doesn't exist in some environments.
                }
            }

            $dropColumns = array_filter([
                Schema::hasColumn('insurances', 'receipt_id') ? 'receipt_id' : null,
                Schema::hasColumn('insurances', 'original_receipt_number') ? 'original_receipt_number' : null,
            ]);

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
}
