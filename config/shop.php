<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seeded admin account
    |--------------------------------------------------------------------------
    |
    | Used by AdminSeeder. In production the seeder only creates an admin when
    | both values are set, so no default credentials ever reach a live server.
    |
    */

    'admin' => [
        'name' => env('SEED_ADMIN_NAME') ?: 'Shop Admin',
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

];
