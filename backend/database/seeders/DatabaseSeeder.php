<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('demo_user.email')],
            [
                'name' => config('demo_user.name'),
                'password' => config('demo_user.password'),
                'email_verified_at' => now(),
            ],
        );
    }
}
