<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-03 y CP-04: registro de familia con habitantes (CU-002) y codigo de vivienda unico (RN-001).
 */
class FamiliasTest extends TestCase
{
    use CreaDatos;

    private function habitante(array $extra = []): array
    {
        return array_merge([
            'cedula'           => 'V-31475286',
            'nombres'          => 'Kevin Andres',
            'apellidos'        => 'Gonzales Torres',
            'fecha_nacimiento' => '2001-06-12',
            'sexo'             => 'masculino',
            'es_discapacitado' => false,
            'nivel_educativo'  => 'bachillerato',
        ], $extra);
    }

    private function datosFamilia($sede, $calle, array $extra = []): array
    {
        return array_merge([
            'codigo_vivienda'    => 'VIV-' . uniqid(),
            'nombre_responsable' => 'Familia Gonzales',
            'sede_id'            => $sede->id,
            'calle_id'           => $calle->id,
            'condicion_vivienda' => 'regular',
            'cantidad_bombonas'  => 2,
            'capacidad_bombona'  => '18kg',
            'posee_agua_potable' => true,
            'posee_luz'          => true,
            'posee_aseo'         => true,
            'punto_referencia'   => 'A 50 metros del porton negro',
            'habitantes'         => [
                $this->habitante(),
                $this->habitante(['cedula' => 'V-31475287', 'nombres' => 'Maria Jose', 'sexo' => 'femenino']),
            ],
        ], $extra);
    }

    private function escenario(): array
    {
        $sede  = $this->crearSede();
        $calle = $this->crearCalle($sede);
        $this->actuarComo('admin');

        return [$sede, $calle];
    }

    // CP-03
    public function test_registra_la_familia_con_sus_habitantes_en_un_solo_envio(): void
    {
        [$sede, $calle] = $this->escenario();
        $datos = $this->datosFamilia($sede, $calle);

        $r = $this->postJson('/api/v1/familias', $datos);

        $r->assertStatus(201);
        $this->assertDatabaseHas('familias', ['codigo_vivienda' => $datos['codigo_vivienda']]);
        $this->assertDatabaseCount('habitantes', 2);
    }

    // CP-03: si un habitante es invalido no se guarda nada
    public function test_si_un_habitante_es_invalido_no_se_guarda_ni_la_familia(): void
    {
        [$sede, $calle] = $this->escenario();
        $datos = $this->datosFamilia($sede, $calle);
        $datos['habitantes'][1]['sexo'] = 'valor-invalido';

        $r = $this->postJson('/api/v1/familias', $datos);

        $r->assertStatus(422);
        $this->assertDatabaseCount('familias', 0);
        $this->assertDatabaseCount('habitantes', 0);
    }

    // CP-04 / RN-001
    public function test_no_permite_un_codigo_de_vivienda_repetido(): void
    {
        [$sede, $calle] = $this->escenario();
        $this->postJson('/api/v1/familias', $this->datosFamilia($sede, $calle, ['codigo_vivienda' => 'VIV-013', 'habitantes' => []]))->assertStatus(201);

        $r = $this->postJson('/api/v1/familias', $this->datosFamilia($sede, $calle, ['codigo_vivienda' => 'VIV-013', 'habitantes' => []]));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['codigo_vivienda']);
        $this->assertDatabaseCount('familias', 1);
    }

    public function test_no_permite_cedulas_repetidas(): void
    {
        [$sede, $calle] = $this->escenario();
        $datos = $this->datosFamilia($sede, $calle);
        $datos['habitantes'][1]['cedula'] = $datos['habitantes'][0]['cedula'];

        $this->postJson('/api/v1/familias', $datos)->assertStatus(422);
        $this->assertDatabaseCount('familias', 0);
    }

    public function test_el_nombre_del_responsable_solo_admite_letras(): void
    {
        [$sede, $calle] = $this->escenario();
        $datos = $this->datosFamilia($sede, $calle, ['nombre_responsable' => 'Perez 12345']);

        $r = $this->postJson('/api/v1/familias', $datos);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['nombre_responsable']);
    }

    public function test_habitante_con_discapacidad_exige_el_tipo(): void
    {
        [$sede, $calle] = $this->escenario();
        $datos = $this->datosFamilia($sede, $calle);
        $datos['habitantes'][0]['es_discapacitado'] = true;
        $datos['habitantes'][0]['tipo_discapacidad'] = '';

        $r = $this->postJson('/api/v1/familias', $datos);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['habitantes.0.tipo_discapacidad']);
    }

    public function test_campos_obligatorios_de_la_familia(): void
    {
        $this->actuarComo('admin');

        $r = $this->postJson('/api/v1/familias', []);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['codigo_vivienda', 'nombre_responsable', 'sede_id', 'calle_id', 'condicion_vivienda', 'cantidad_bombonas', 'capacidad_bombona']);
    }

    public function test_el_vocero_no_puede_registrar_familias(): void
    {
        $sede  = $this->crearSede();
        $calle = $this->crearCalle($sede);
        $this->actuarComo('vocero');

        $this->postJson('/api/v1/familias', $this->datosFamilia($sede, $calle))->assertStatus(403);
        $this->assertDatabaseCount('familias', 0);
    }
}
