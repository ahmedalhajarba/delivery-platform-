<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSubCategoryToStrategicCounterpartiesTable extends Migration
{
    protected $connection = 'strategic_finance';

    public function up()
    {
        if (
            Schema::connection($this->connection)->hasTable('sf_counterparties') &&
            !Schema::connection($this->connection)->hasColumn('sf_counterparties', 'sub_category')
        ) {
            Schema::connection($this->connection)->table('sf_counterparties', function (Blueprint $table) {
                $table->string('sub_category', 50)->nullable()->after('category');
                $table->index(['category', 'sub_category', 'is_active'], 'sf_counterparties_category_subcategory_active_idx');
            });
        }
    }

    public function down()
    {
        if (
            Schema::connection($this->connection)->hasTable('sf_counterparties') &&
            Schema::connection($this->connection)->hasColumn('sf_counterparties', 'sub_category')
        ) {
            Schema::connection($this->connection)->table('sf_counterparties', function (Blueprint $table) {
                $table->dropIndex('sf_counterparties_category_subcategory_active_idx');
                $table->dropColumn('sub_category');
            });
        }
    }
    // migration removed as per user request
}
