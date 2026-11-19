<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-10, CP-18, CP-19 y pruebas de seguridad ST-02 a ST-04.
 */
class ControlAccesoTest extends TestCase
{
    use CreaDatos;

    /** Rutas que solo puede usar el administrador. */
    private array $rutasSoloAdmin = [
        '/api/v1/familias',
        '/api/v1/usuarios',
        '/api/v1/auditorias',
        '/api/v1/configuracion/permisos',
    ];

    /** Rutas de consulta para administrador y vocero. */
    private array $rutasAdminYVocero = [
        '/api/v1/voceros',
        '/api/v1/proyectos',
        '/api/v1/beneficios',
        '/api/v1/organizaciones',
        '/api/v1/pagos',
    ];

    // ST-02 / CP-19
    public function test_sin_token_todas_las_rutas_protegidas_devuelven_401(): void
    {
        foreach (array_merge($this->rutasSoloAdmin, $this->rutasAdminYVocero) as $ruta) {
            $this->app['auth']->forgetGuards();
            $this->getJson($ruta)->assertStatus(401);
        }
    }

    // ST-03
    public function test_usuario_ciudadano_no_accede_a_modulos_administrativos(): void
    {
        $this->actuarComo('usuario');

        foreach (array_merge($this->rutasSoloAdmin, $this->rutasAdminYVocero) as $ruta) {
            $r = $this->getJson($ruta);
            $this->assertSame(403, $r->getStatusCode(), "El ciudadano no debe acceder a {$ruta}");
        }
    }

    // ST-04 / CP-18
    public function test_vocero_no_accede_al_censo_ni_a_la_administracion(): void
    {
        $this->actuarComo('vocero');

        foreach ($this->rutasSoloAdmin as $ruta) {
            $r = $this->getJson($ruta);
            $this->assertSame(403, $r->getStatusCode(), "El vocero no debe acceder a {$ruta}");
        }
    }

    public function test_vocero_si_puede_consultar_los_modulos_de_lectura(): void
    {
        $this->actuarComo('vocero');

        foreach ($this->rutasAdminYVocero as $ruta) {
            $r = $this->getJson($ruta);
            $this->assertSame(200, $r->getStatusCode(), "El vocero debe poder consultar {$ruta}");
        }
    }

    public function test_administrador_accede_a_los_modulos_administrativos(): void
    {
        $this->actuarComo('admin');

        foreach ($this->rutasSoloAdmin as $ruta) {
            $r = $this->getJson($ruta);
            $this->assertSame(200, $r->getStatusCode(), "El administrador debe acceder a {$ruta}");
        }
    }

    // CP-10: el vocero no puede registrar pagos a nombre de un habitante
    public function test_vocero_no_puede_registrar_pago_a_nombre_de_un_habitante(): void
    {
        $this->actuarComo('vocero');

        $this->postJson('/api/v1/pagos', [])->assertStatus(403);
    }

    public function test_vocero_no_puede_crear_voceros(): void
    {
        $this->actuarComo('vocero');

        $this->postJson('/api/v1/voceros', [])->assertStatus(403);
    }

    public function test_rutas_exclusivas_del_vocero_rechazan_a_otros_roles(): void
    {
        $this->actuarComo('usuario');
        $this->getJson('/api/v1/vocero/resumen')->assertStatus(403);

        $this->actuarComo('admin');
        $this->getJson('/api/v1/vocero/resumen')->assertStatus(403);
    }
}
