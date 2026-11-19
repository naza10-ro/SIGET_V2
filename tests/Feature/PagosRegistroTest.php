<?php

namespace Tests\Feature;

use App\Models\PagoQr;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-07, CP-08, CP-09 y ST-10: registro de pagos por QR (CU-004 y CU-005).
 */
class PagosRegistroTest extends TestCase
{
    use CreaDatos;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function datosPago($cuenta, array $extra = []): array
    {
        return array_merge([
            'cuenta_pago_id'      => $cuenta->id,
            'metodo_pago'         => 'pago_movil',
            'referencia_bancaria' => 'REF-' . uniqid(),
            'monto'               => 15.50,
            'concepto_pago'       => 'Cuota de gas',
            'fecha_pago'          => now()->subDay()->toDateString(),
            'comprobante'         => $this->comprobantePng(),
        ], $extra);
    }

    private function enviarComoCiudadano(array $datos)
    {
        return $this->post('/api/v1/pagos/mio', $datos, ['Accept' => 'application/json']);
    }

    // CP-07
    public function test_ciudadano_envia_un_pago_valido_y_queda_pendiente(): void
    {
        $cuenta = $this->crearCuentaPago();
        $usuario = $this->actuarComo('usuario');

        $datos = $this->datosPago($cuenta);
        $r = $this->enviarComoCiudadano($datos);

        $this->assertContains($r->getStatusCode(), [200, 201], 'No se registro el pago: ' . $r->getContent());
        $pago = PagoQr::where('referencia_bancaria', $datos['referencia_bancaria'])->firstOrFail();
        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame($usuario->id, (int) $pago->user_id);
    }

    // CP-08 / RN-007
    public function test_no_permite_una_referencia_bancaria_repetida(): void
    {
        $cuenta = $this->crearCuentaPago();
        $this->actuarComo('usuario');
        $datos = $this->datosPago($cuenta, ['referencia_bancaria' => 'REF-DUPLICADA-1']);

        $this->enviarComoCiudadano($datos)->assertSuccessful();
        $segundo = $this->enviarComoCiudadano($this->datosPago($cuenta, ['referencia_bancaria' => 'REF-DUPLICADA-1']));

        $segundo->assertStatus(422);
        $segundo->assertJsonValidationErrors(['referencia_bancaria']);
        $this->assertSame(1, PagoQr::where('referencia_bancaria', 'REF-DUPLICADA-1')->count());
    }

    // RN-007: el metodo debe coincidir con el de la cuenta
    public function test_el_metodo_debe_coincidir_con_el_de_la_cuenta(): void
    {
        $cuenta = $this->crearCuentaPago(); // cuenta de pago movil
        $this->actuarComo('usuario');

        $r = $this->enviarComoCiudadano($this->datosPago($cuenta, ['metodo_pago' => 'transferencia', 'banco_origen' => 'Banesco']));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['metodo_pago']);
    }

    // RN-007: fecha de los ultimos 30 dias
    public function test_la_fecha_del_pago_no_puede_tener_mas_de_30_dias(): void
    {
        $cuenta = $this->crearCuentaPago();
        $this->actuarComo('usuario');

        $r = $this->enviarComoCiudadano($this->datosPago($cuenta, ['fecha_pago' => now()->subDays(45)->toDateString()]));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['fecha_pago']);
    }

    public function test_la_fecha_del_pago_no_puede_ser_futura(): void
    {
        $cuenta = $this->crearCuentaPago();
        $this->actuarComo('usuario');

        $r = $this->enviarComoCiudadano($this->datosPago($cuenta, ['fecha_pago' => now()->addDays(3)->toDateString()]));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['fecha_pago']);
    }

    // ST-10
    public function test_el_comprobante_debe_ser_una_imagen(): void
    {
        $cuenta = $this->crearCuentaPago();
        $this->actuarComo('usuario');
        $archivo = \Illuminate\Http\UploadedFile::fake()->createWithContent('virus.txt', 'esto no es una imagen');

        $r = $this->enviarComoCiudadano($this->datosPago($cuenta, ['comprobante' => $archivo]));

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['comprobante']);
    }

    public function test_campos_obligatorios_del_pago(): void
    {
        $this->actuarComo('usuario');

        $r = $this->post('/api/v1/pagos/mio', [], ['Accept' => 'application/json']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['cuenta_pago_id', 'metodo_pago', 'referencia_bancaria', 'monto', 'concepto_pago', 'fecha_pago', 'comprobante']);
    }

    // CP-09
    public function test_administrador_registra_un_pago_a_nombre_de_un_habitante(): void
    {
        $familia   = $this->crearFamilia($this->crearSede());
        $habitante = $this->crearHabitante($familia);
        $cuenta    = $this->crearCuentaPago();
        $this->actuarComo('admin');

        $datos = $this->datosPago($cuenta, ['habitante_id' => $habitante->id]);
        $r = $this->post('/api/v1/pagos', $datos, ['Accept' => 'application/json']);

        $this->assertContains($r->getStatusCode(), [200, 201], 'No se registro el pago: ' . $r->getContent());
        $pago = PagoQr::where('referencia_bancaria', $datos['referencia_bancaria'])->firstOrFail();
        $this->assertSame('pendiente', $pago->estado);
        $this->assertSame($habitante->id, (int) $pago->habitante_id);
        $this->assertSame($familia->id, (int) $pago->familia_id, 'La familia debe deducirse del habitante.');
    }

    public function test_el_pago_a_nombre_de_un_habitante_exige_un_habitante_existente(): void
    {
        $cuenta = $this->crearCuentaPago();
        $this->actuarComo('admin');

        $r = $this->post('/api/v1/pagos', $this->datosPago($cuenta, ['habitante_id' => 999999]), ['Accept' => 'application/json']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['habitante_id']);
    }
}
