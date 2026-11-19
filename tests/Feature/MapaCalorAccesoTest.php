<?php

namespace Tests\Feature;

use App\Models\Vocero;
use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-16 y regla RN-006: el vocero solo ve las calles de su sede en el mapa de calor.
 */
class MapaCalorAccesoTest extends TestCase
{
    use CreaDatos;

    private function escenario(): array
    {
        $encuesta = $this->crearEncuestaGas();
        $sedeA    = $this->crearSede();
        $sedeB    = $this->crearSede();
        $calleA   = $this->crearCalle($sedeA);
        $calleB   = $this->crearCalle($sedeB);
        $familia  = $this->crearFamilia($sedeA, $calleA);
        $habitante = $this->crearHabitante($familia);

        return [$encuesta, $sedeA, $calleA, $calleB, $habitante];
    }

    private function actuarComoVocero($habitante, $sede, bool $activo = true)
    {
        $usuario = $this->crearUsuario('vocero', ['habitante_id' => $habitante->id]);
        Vocero::forceCreate([
            'habitante_id'   => $habitante->id,
            'sede_id'        => $sede->id,
            'unidad_comunal' => 'Comite de prueba',
            'fecha_eleccion' => '2025-01-01',
            'esta_activo'    => $activo,
        ]);
        $this->app['auth']->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($usuario, ['*']);

        return $usuario;
    }

    private function idsDeCalles($respuesta): array
    {
        return collect($respuesta->json('data'))->pluck('calle_id')->map(fn ($id) => (int) $id)->all();
    }

    // CP-16
    public function test_el_vocero_solo_ve_las_calles_de_su_sede(): void
    {
        [$encuesta, $sedeA, $calleA, $calleB, $habitante] = $this->escenario();
        $this->actuarComoVocero($habitante, $sedeA);

        $r = $this->getJson('/api/v1/mapa-calor?encuesta_id=' . $encuesta->id);

        $r->assertStatus(200);
        $ids = $this->idsDeCalles($r);
        $this->assertContains($calleA->id, $ids);
        $this->assertNotContains($calleB->id, $ids, 'El vocero no debe ver calles de otra sede.');
    }

    public function test_el_administrador_ve_las_calles_de_todas_las_sedes(): void
    {
        [$encuesta, , $calleA, $calleB] = $this->escenario();
        $this->actuarComo('admin');

        $r = $this->getJson('/api/v1/mapa-calor?encuesta_id=' . $encuesta->id);

        $r->assertStatus(200);
        $ids = $this->idsDeCalles($r);
        $this->assertContains($calleA->id, $ids);
        $this->assertContains($calleB->id, $ids);
    }

    public function test_el_vocero_no_puede_ver_el_detalle_de_una_calle_de_otra_sede(): void
    {
        [$encuesta, $sedeA, , $calleB, $habitante] = $this->escenario();
        $this->actuarComoVocero($habitante, $sedeA);

        $this->getJson("/api/v1/mapa-calor/calle/{$calleB->id}?encuesta_id=" . $encuesta->id)->assertStatus(403);
    }

    public function test_un_vocero_sin_mandato_activo_no_accede_al_mapa(): void
    {
        [$encuesta, $sedeA, , , $habitante] = $this->escenario();
        $this->actuarComoVocero($habitante, $sedeA, false);

        $this->getJson('/api/v1/mapa-calor?encuesta_id=' . $encuesta->id)->assertStatus(403);
    }

    public function test_el_ciudadano_no_accede_al_mapa(): void
    {
        [$encuesta] = $this->escenario();
        $this->actuarComo('usuario');

        $this->getJson('/api/v1/mapa-calor?encuesta_id=' . $encuesta->id)->assertStatus(403);
    }
}
