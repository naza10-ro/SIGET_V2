<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-05 y CP-06: constancia de residencia (CU-003).
 */
class ConstanciaTest extends TestCase
{
    use CreaDatos;

    private function habitanteConFamilia()
    {
        $sede      = $this->crearSede();
        $calle     = $this->crearCalle($sede);
        $familia   = $this->crearFamilia($sede, $calle);

        return [$familia, $this->crearHabitante($familia)];
    }

    // CP-05
    public function test_el_administrador_descarga_la_constancia_en_pdf(): void
    {
        [, $habitante] = $this->habitanteConFamilia();
        $this->actuarComo('admin');

        $r = $this->get("/api/v1/reportes/constancia-residencia/{$habitante->id}");

        $r->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
    }

    public function test_el_ciudadano_no_puede_descargar_constancias(): void
    {
        [, $habitante] = $this->habitanteConFamilia();
        $this->actuarComo('usuario');

        $this->getJson("/api/v1/reportes/constancia-residencia/{$habitante->id}")->assertStatus(403);
    }

    // Reporte de estadisticas del dashboard
    public function test_el_administrador_descarga_las_estadisticas_en_pdf(): void
    {
        $this->actuarComo('admin');

        $r = $this->get('/api/v1/reportes/estadisticas-dashboard');

        $r->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $r->headers->get('content-type'));
    }

    // CP-06: habitante cuya familia fue dada de baja -> error controlado, no un PDF ni un error 500
    public function test_la_constancia_de_un_habitante_sin_familia_activa_da_error_controlado(): void
    {
        [$familia, $habitante] = $this->habitanteConFamilia();
        $familia->delete(); // baja logica de la familia
        $this->actuarComo('admin');

        $r = $this->getJson("/api/v1/reportes/constancia-residencia/{$habitante->id}");

        $this->assertSame(422, $r->getStatusCode(), 'Se esperaba un error controlado 422 y se obtuvo ' . $r->getStatusCode());
    }
}
