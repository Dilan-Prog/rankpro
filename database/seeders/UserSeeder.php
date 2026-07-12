<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Default admin account so the panel is reachable right after seeding.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        User::updateOrCreate(
            ['email' => 'admin@rankpro.test'],
            [
                'name' => 'Admin RankPro',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
