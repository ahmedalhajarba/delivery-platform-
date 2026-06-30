<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProfileFieldsToStrategicLegacyCompaniesTable extends Migration
{
    protected $connection = 'strategic_finance';

    public function up()
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('sf_legacy_companies')) {
            $schema->table('sf_legacy_companies', function (Blueprint $table) use ($schema) {
                if (!$schema->hasColumn('sf_legacy_companies', 'owner_name')) {
                    $table->string('owner_name')->nullable()->after('name');
                }

                if (!$schema->hasColumn('sf_legacy_companies', 'founded_at')) {
                    $table->date('founded_at')->nullable()->after('owner_name');
                }

                if (!$schema->hasColumn('sf_legacy_companies', 'bankrupt_at')) {
                    $table->date('bankrupt_at')->nullable()->after('founded_at');
                }

                if (!$schema->hasColumn('sf_legacy_companies', 'ceased_activity_at')) {
                    $table->date('ceased_activity_at')->nullable()->after('bankrupt_at');
                }

                if (!$schema->hasColumn('sf_legacy_companies', 'company_milestones')) {
                    $table->text('company_milestones')->nullable()->after('ceased_activity_at');
                }
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection($this->connection);

        if ($schema->hasTable('sf_legacy_companies')) {
            $schema->table('sf_legacy_companies', function (Blueprint $table) use ($schema) {
                $columns = [
                    'owner_name',
                    'founded_at',
                    'bankrupt_at',
                    'ceased_activity_at',
                    'company_milestones',
                ];

                $existingColumns = array_values(array_filter($columns, function ($column) use ($schema) {
                    return $schema->hasColumn('sf_legacy_companies', $column);
                }));

                if (!empty($existingColumns)) {
                    $table->dropColumn($existingColumns);
                }
            });
        }
    }
}
