<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Only seed dev user in local/dev environments
        if (app()->environment(['local', 'development'])) {
            $devUser = \App\Models\User::firstOrCreate(
                ['email' => 'dev@orvis.local'],
                [
                    'name' => 'Orvis Dev User',
                    'password' => bcrypt('password'),
                ]
            );

            // Create a long-lived token for dev use
            // Delete existing tokens to ensure clean state
            $devUser->tokens()->delete();

            $token = $devUser->createToken('dev-token')->plainTextToken;

            $this->command->info('==============================================');
            $this->command->info('DEV USER CREATED:');
            $this->command->info('Email: dev@orvis.local');
            $this->command->info('Password: password');
            $this->command->info('==============================================');
            $this->command->info('DEV BEARER TOKEN:');
            $this->command->info($token);
            $this->command->info('==============================================');
            $this->command->info('Use this token in your React app Authorization header:');
            $this->command->info('Authorization: Bearer ' . $token);
            $this->command->info('==============================================');
        }
    }
}
