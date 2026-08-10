<?php

namespace Tests;

use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // `usuario.rol` es clave foránea contra `rol.codigo`: sin el catálogo de
    // roles no se puede crear ningún usuario en las pruebas.
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
    }
}
