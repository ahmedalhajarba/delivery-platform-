<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSecurityColumnsToAuditLogsTable extends Migration
{
    public function up()
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->uuid('event_uuid')->nullable()->after('id');
            $table->string('action')->nullable()->after('description');
            $table->string('request_method', 10)->nullable()->after('host');
            $table->text('request_url')->nullable()->after('request_method');
            $table->string('user_agent')->nullable()->after('request_url');

            $table->index('event_uuid');
            $table->index('action');
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event_uuid']);
            $table->dropIndex(['action']);
            $table->dropIndex(['subject_type', 'subject_id']);
            $table->dropIndex(['created_at']);

            $table->dropColumn([
                'event_uuid',
                'action',
                'request_method',
                'request_url',
                'user_agent',
            ]);
        });
    }
}
