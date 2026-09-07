<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferentialDataSeeder::class,
        ]);

        $principal = User::updateOrCreate(
            ['email' => 'admin@wecko.test'],
            [
                'name' => 'Dr. Wecko Principal',
                'password' => bcrypt('password'),
                'is_provider' => true,
                'is_principal' => true,
                'can_login' => true,
                'email_verified_at' => now(),
            ],
        );
        $principal->syncRoles(['principal']);

        $this->call([
            DemoSeeder::class,
        ]);
    }
}
