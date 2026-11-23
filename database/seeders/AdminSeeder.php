<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the shop admin account.
     *
     * Locally this falls back to admin@example.com / password. In production the
     * SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD environment values are required.
     */
    public function run(): void
    {
        $production = app()->isProduction();

        $email = config('shop.admin.email') ?: ($production ? null : 'admin@example.com');
        $password = config('shop.admin.password') ?: ($production ? null : 'password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('Skipped admin user: set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD to create one.');

            return;
        }

        if (User::where('email', $email)->exists()) {
            return;
        }

        User::factory()->admin()->create([
            'name' => config('shop.admin.name'),
            'email' => $email,
            'password' => $password,
        ]);
    }
}
