<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\Role::firstOrCreate(['id' => 1], ['nombre_rol' => 'admin']);
        \App\Models\Role::firstOrCreate(['id' => 2], ['nombre_rol' => 'usuario']);
        \App\Models\Role::firstOrCreate(['id' => 3], ['nombre_rol' => 'pro']);
        \App\Models\Role::firstOrCreate(['id' => 4], ['nombre_rol' => 'ultra']);
    }
}
