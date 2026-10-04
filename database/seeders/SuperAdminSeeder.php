<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@spms.test')],
            [
                'name'           => 'Super Admin',
                'password'       => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
                'is_super_admin' => true,
                'company_id'     => null,
            ]
        );
    }
}
