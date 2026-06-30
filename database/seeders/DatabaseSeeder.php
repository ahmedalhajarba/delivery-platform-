<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            PermissionRoleTableSeeder::class,
            RoleProfilesSeeder::class,
            UsersTableSeeder::class,
            RoleUserTableSeeder::class,
            CountriesTableSeeder::class,
            SettingsSeeder::class,
            LegalResponsibilityPageSeeder::class,
            CountriesCitySeeder::class,
        ]);
    }
}
