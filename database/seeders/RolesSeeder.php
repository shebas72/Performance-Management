<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // admin   = full access within the company
        // manager = can edit strategy data
        // viewer  = read-only
        foreach (['admin', 'manager', 'viewer'] as $role) {
            Role::findOrCreate($role);
        }

        // Give existing tenant users (e.g. demo users) the admin role if they have none.
        User::where('is_super_admin', false)->get()->each(function (User $u) {
            if ($u->roles()->count() === 0) {
                $u->assignRole('admin');
            }
        });
    }
}
