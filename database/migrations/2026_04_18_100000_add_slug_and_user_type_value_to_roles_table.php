<?php
// database/migrations/2026_04_18_100000_add_slug_and_user_type_value_to_roles_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->integer('user_type_value')->nullable()->after('slug');
            $table->boolean('is_default')->default(false)->after('user_type_value');
        });
    }

    public function down()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['slug', 'user_type_value', 'is_default']);
        });
    }
};
