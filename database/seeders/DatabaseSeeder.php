<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolSeeder::class,
            PermisoSeeder::class,
            UsuarioSeeder::class,
            InmuebleSeeder::class,
            OperacionSeeder::class,
        ]);
    }
}
