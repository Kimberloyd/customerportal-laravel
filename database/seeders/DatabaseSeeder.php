<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. Since this app now owns its
     * database outright (no more inheriting accounts from the retired
     * Flask app's shared schema), a fresh `db` container has nothing to
     * log in with until this creates the first admin account.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'full_name' => 'Admin',
            'email' => 'admin@theomeds.com',
        ]);
    }
}
