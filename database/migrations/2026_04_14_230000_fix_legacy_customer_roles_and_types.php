<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')
            ->whereIn(DB::raw('LOWER(title)'), ['customer', 'coustomer'])
            ->value('id');

        // Normalize any legacy misspelling to a single user_type.
        DB::table('users')
            ->whereRaw('LOWER(user_type) in (?, ?)', ['customer', 'coustomer'])
            ->update(['user_type' => 'customer']);

        if (!$roleId) {
            return;
        }

        $customerUserIds = DB::table('users')
            ->whereRaw('LOWER(user_type) = ?', ['customer'])
            ->pluck('id');

        foreach ($customerUserIds as $userId) {
            $exists = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->exists();

            if (!$exists) {
                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep data-fix migration irreversible to avoid accidental data regression.
    }
};
