<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('last_name');
            }

            if (!Schema::hasColumn('users', 'login_code')) {
                $table->string('login_code', 11)->nullable()->unique()->after('username');
            }
        });

        DB::table('users')
            ->select('id', 'name', 'username', 'login_code')
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $updates = [];

                    if (empty($user->username)) {
                        $base = Str::slug((string) $user->name, '_');
                        if ($base === '') {
                            $base = 'user';
                        }

                        $candidate = $base . '_' . $user->id;
                        $counter = 1;
                        while (DB::table('users')->where('username', $candidate)->where('id', '!=', $user->id)->exists()) {
                            $candidate = $base . '_' . $user->id . '_' . $counter;
                            $counter++;
                        }

                        $updates['username'] = $candidate;
                    }

                    if (empty($user->login_code)) {
                        do {
                            $digits = str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
                            $code = substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3);
                        } while (DB::table('users')->where('login_code', $code)->where('id', '!=', $user->id)->exists());

                        $updates['login_code'] = $code;
                    }

                    if (!empty($updates)) {
                        DB::table('users')->where('id', $user->id)->update($updates);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'login_code')) {
                $table->dropUnique('users_login_code_unique');
                $table->dropColumn('login_code');
            }

            if (Schema::hasColumn('users', 'username')) {
                $table->dropUnique('users_username_unique');
                $table->dropColumn('username');
            }
        });
    }
};
