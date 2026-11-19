<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-17: un habitante no puede tener dos cargos de vocero activos (RN-003).
 */
class VocerosReglasTest extends TestCase
{
    use CreaDatos;

    private function datosVocero($habitante, $sede, array $extra = []): array
    {
        return array_merge([
            'habitante_id'   => $habitante->id,
            'sede_id'        => $sede->id,
            'unidad_comunal' => 'Comite de Salud',
            'fecha_eleccion' => '2025-03-15',
        ], $extra);
    }

    private function prepararEscenario(): array
    {
        $sede      = $this->crearSede();
        $calle     = $this->crearCalle($sede);
        $familia   = $this->crearFamilia($sede, $calle);
        $habitante = $this->crearHabitante($familia);

        return [$sede, $habitante];
    }

    public function test_administrador_puede_registrar_un_vocero(): void
    {
        [$sede, $habitante] = $this->prepararEscenario();
        $this->actuarComo('admin');

        $r = $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede));

        $this->assertContains($r->getStatusCode(), [200, 201], 'No se pudo registrar el vocero: ' . $r->getContent());
        $this->assertDatabaseHas('voceros', ['habitante_id' => $habitante->id, 'esta_activo' => true]);
    }

    // CP-17
    public function test_no_permite_dos_mandatos_activos_del_mismo_habitante(): void
    {
        [$sede, $habitante] = $this->prepararEscenario();
        $this->actuarComo('admin');

        $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede));
        $segundo = $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede, ['unidad_comunal' => 'Comite de Agua']));

        $segundo->assertStatus(422);
        $segundo->assertJsonValidationErrors(['habitante_id']);
        $this->assertDatabaseCount('voceros', 1);
    }

    public function test_la_fecha_de_eleccion_no_puede_ser_futura(): void
    {
        [$sede, $habitante] = $this->prepararEscenario();
        $this->actuarComo('admin');

        $r = $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede, [
            'fecha_eleccion' => now()->addYear()->format('Y-m-d'),
        ]));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['fecha_eleccion']);
    }

    public function test_campos_obligatorios_del_vocero(): void
    {
        $this->actuarComo('admin');

        $r = $this->postJson('/api/v1/voceros', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['habitante_id', 'sede_id', 'unidad_comunal', 'fecha_eleccion']);
    }

    // El historial se conserva: al desactivar el mandato se puede asignar uno nuevo
    public function test_desactivar_el_mandato_permite_registrar_uno_nuevo(): void
    {
        [$sede, $habitante] = $this->prepararEscenario();
        $this->actuarComo('admin');

        $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede));
        $vocero = \App\Models\Vocero::where('habitante_id', $habitante->id)->firstOrFail();

        $this->deleteJson('/api/v1/voceros/' . $vocero->id);

        $this->assertFalse((bool) $vocero->fresh()->esta_activo, 'El mandato debio quedar inactivo.');

        $nuevo = $this->postJson('/api/v1/voceros', $this->datosVocero($habitante, $sede, ['unidad_comunal' => 'Comite de Agua']));
        $this->assertContains($nuevo->getStatusCode(), [200, 201]);
        $this->assertDatabaseCount('voceros', 2);
    }
}
