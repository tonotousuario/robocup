<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            InstitucionSeeder::class,
            CategoriaSeeder::class,
            TarifaSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'admin@roboleague.test'],
            [
                'name' => 'Admin',
                'apellidos' => 'RoboLeague',
                'rol' => 'Administrador',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => Str::random(10),
            ]
        );
    }
}
