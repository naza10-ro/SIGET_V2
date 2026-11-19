<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase {
        refreshDatabase as protected refrescarBaseOriginal;
    }

    /**
     * SEGURIDAD: RefreshDatabase borra y recrea todas las tablas.
     * Por eso las pruebas se bloquean si la base conectada no es siget_test.
     */
    public function refreshDatabase()
    {
        $base = DB::connection()->getDatabaseName();

        if ($base !== 'siget_test') {
            throw new \RuntimeException(
                "PRUEBAS BLOQUEADAS: la base conectada es '{$base}' y debe ser 'siget_test'."
            );
        }

        $this->refrescarBaseOriginal();
    }
}
