<?php

namespace Tests\Feature;

use App\Models\Encuesta;
use App\Models\EncuestaRespuesta;
use App\Services\EstimadorConsumoService;
use Carbon\Carbon;
use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-15: estimador de consumo del mapa de calor (CU-008).
 */
class EstimadorConsumoTest extends TestCase
{
    use CreaDatos;

    private function encuestaGas(): Encuesta
    {
        return Encuesta::forceCreate([
            'nombre' => 'Censo de gas de prueba',
            'tipo'   => Encuesta::TIPO_GAS,
            'activa' => true,
        ]);
    }

    /** Registra entregas hace N dias para una familia. */
    private function entregas(Encuesta $encuesta, $familia, array $diasAtras): void
    {
        foreach ($diasAtras as $dias) {
            EncuestaRespuesta::forceCreate([
                'encuesta_id'   => $encuesta->id,
                'familia_id'    => $familia->id,
                'cantidad'      => 1,
                'fecha_entrega' => Carbon::today()->subDays($dias)->toDateString(),
            ]);
        }
    }

    private function estado($familia): string
    {
        $resultado = app(EstimadorConsumoService::class)->estimarPorFamilias([$familia->id]);

        return $resultado->get($familia->id)['estado'];
    }

    public function test_con_una_sola_entrega_no_hay_datos(): void
    {
        $encuesta = $this->encuestaGas();
        $familia  = $this->crearFamilia();
        $this->entregas($encuesta, $familia, [10]);

        $this->assertSame('sin_datos', $this->estado($familia));
    }

    public function test_varias_entregas_el_mismo_dia_cuentan_como_una(): void
    {
        $encuesta = $this->encuestaGas();
        $familia  = $this->crearFamilia();
        $this->entregas($encuesta, $familia, [10, 10]);

        $this->assertSame('sin_datos', $this->estado($familia));
    }

    public function test_familia_al_dia_con_mas_de_siete_dias_restantes(): void
    {
        $encuesta = $this->encuestaGas();
        $familia  = $this->crearFamilia();
        // Entregas separadas 20 dias; la ultima hace 5 dias -> faltan 15 dias.
        $this->entregas($encuesta, $familia, [25, 5]);

        $this->assertSame('al_dia', $this->estado($familia));
    }

    public function test_familia_proxima_con_siete_dias_o_menos(): void
    {
        $encuesta = $this->encuestaGas();
        $familia  = $this->crearFamilia();
        // Promedio 20 dias; la ultima hace 15 dias -> faltan 5 dias.
        $this->entregas($encuesta, $familia, [35, 15]);

        $this->assertSame('proximo', $this->estado($familia));
    }

    public function test_familia_vencida_cuando_ya_paso_la_fecha_estimada(): void
    {
        $encuesta = $this->encuestaGas();
        $familia  = $this->crearFamilia();
        // Promedio 20 dias; la ultima hace 25 dias -> se agoto hace 5 dias.
        $this->entregas($encuesta, $familia, [45, 25]);

        $this->assertSame('vencido', $this->estado($familia));
    }

    public function test_la_calle_toma_el_riesgo_de_su_peor_familia(): void
    {
        $encuesta = $this->encuestaGas();
        $sede     = $this->crearSede();
        $calle    = $this->crearCalle($sede);

        $alDia   = $this->crearFamilia($sede, $calle);
        $vencida = $this->crearFamilia($sede, $calle);
        $this->entregas($encuesta, $alDia, [25, 5]);
        $this->entregas($encuesta, $vencida, [45, 25]);

        $resumen = app(EstimadorConsumoService::class)->resumenPorCalles([$calle->id]);

        $this->assertSame(2, $resumen[$calle->id]['riesgo'], 'Con una familia vencida el riesgo de la calle debe ser 2.');
        $this->assertSame(2, $resumen[$calle->id]['total_familias']);
    }

    public function test_calle_sin_datos_suficientes_no_se_pinta_de_verde(): void
    {
        $this->encuestaGas();
        $sede  = $this->crearSede();
        $calle = $this->crearCalle($sede);
        $this->crearFamilia($sede, $calle);

        $resumen = app(EstimadorConsumoService::class)->resumenPorCalles([$calle->id]);

        $this->assertNull($resumen[$calle->id]['riesgo'], 'Sin datos el riesgo debe ser null (gris).');
    }
}
