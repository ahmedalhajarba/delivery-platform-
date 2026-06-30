<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GiveAdminAllPermissionsSeeder extends Seeder
{
    public function run()
    {
        DB::statement("INSERT IGNORE INTO role_has_permissions (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug = 'admin'");
    }
}
