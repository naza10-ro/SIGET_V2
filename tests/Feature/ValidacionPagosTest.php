<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-11, CP-12 y regla RN-004: validacion obligatoria de pagos.
 */
class ValidacionPagosTest extends TestCase
{
    use CreaDatos;

    private function url($pago): string
    {
        return '/api/v1/pagos/' . $pago->id . '/validar';
    }

    // CP-11
    public function test_vocero_puede_verificar_un_pago_pendiente(): void
    {
        $pago   = $this->crearPagoPendiente();
        $vocero = $this->actuarComo('vocero');

        $r = $this->patchJson($this->url($pago), ['estado' => 'verificado']);

        $r->assertStatus(200);
        $pago = $pago->fresh();
        $this->assertSame('verificado', $pago->estado);
        $this->assertSame($vocero->id, (int) $pago->verificado_por);
        $this->assertNotNull($pago->fecha_verificacion);
    }

    public function test_administrador_puede_verificar_un_pago_pendiente(): void
    {
        $pago = $this->crearPagoPendiente();
        $this->actuarComo('admin');

        $this->patchJson($this->url($pago), ['estado' => 'verificado'])->assertStatus(200);

        $this->assertSame('verificado', $pago->fresh()->estado);
    }

    // Un pago ya procesado no se puede modificar
    public function test_un_pago_ya_verificado_no_puede_modificarse(): void
    {
        $pago = $this->crearPagoPendiente();
        $this->actuarComo('admin');

        $this->patchJson($this->url($pago), ['estado' => 'verificado'])->assertStatus(200);
        $segundo = $this->patchJson($this->url($pago), ['estado' => 'rechazado', 'motivo_rechazo' => 'Prueba']);

        $segundo->assertStatus(422);
        $this->assertSame('verificado', $pago->fresh()->estado);
    }

    // CP-12: el rechazo exige motivo
    public function test_rechazar_sin_motivo_es_rechazado_por_el_servidor(): void
    {
        $pago = $this->crearPagoPendiente();
        $this->actuarComo('vocero');

        $r = $this->patchJson($this->url($pago), ['estado' => 'rechazado']);

        $r->assertStatus(422);
        $this->assertSame('pendiente', $pago->fresh()->estado);
    }

    public function test_rechazar_con_motivo_guarda_el_motivo(): void
    {
        $pago = $this->crearPagoPendiente();
        $this->actuarComo('vocero');

        $r = $this->patchJson($this->url($pago), ['estado' => 'rechazado', 'motivo_rechazo' => 'Referencia no encontrada en el banco']);

        $r->assertStatus(200);
        $pago = $pago->fresh();
        $this->assertSame('rechazado', $pago->estado);
        $this->assertSame('Referencia no encontrada en el banco', $pago->motivo_rechazo);
    }

    public function test_el_ciudadano_no_puede_validar_pagos(): void
    {
        $pago = $this->crearPagoPendiente();
        $this->actuarComo('usuario');

        $this->patchJson($this->url($pago), ['estado' => 'verificado'])->assertStatus(403);

        $this->assertSame('pendiente', $pago->fresh()->estado);
    }
}
