<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-01, CP-02 y CP-19 (parte de la API) del plan de pruebas.
 */
class AutenticacionTest extends TestCase
{
    use CreaDatos;

    private function iniciarSesion(string $email, string $clave)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/login', ['email' => $email, 'password' => $clave]);
    }

    private function consultarMe(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson('/api/v1/me', ['Authorization' => 'Bearer ' . $token]);
    }

    // CP-01
    public function test_login_con_credenciales_validas_entrega_token(): void
    {
        $usuario = $this->crearUsuario('admin');

        $respuesta = $this->iniciarSesion($usuario->email, 'Password123!');

        $respuesta->assertStatus(200);
        $this->assertNotEmpty($respuesta->json('data.token'), 'El login no devolvio el token.');
    }

    // CP-01 (variante): credenciales incorrectas
    public function test_login_con_clave_incorrecta_devuelve_401(): void
    {
        $usuario = $this->crearUsuario('usuario');

        $respuesta = $this->iniciarSesion($usuario->email, 'ClaveIncorrecta1');

        $respuesta->assertStatus(401);
    }

    public function test_login_con_correo_inexistente_devuelve_401(): void
    {
        $respuesta = $this->iniciarSesion('no.existe@siget.test', 'Password123!');

        $respuesta->assertStatus(401);
    }

    // CP-02
    public function test_cuenta_se_bloquea_tras_tres_intentos_fallidos(): void
    {
        $usuario = $this->crearUsuario('usuario');

        $this->iniciarSesion($usuario->email, 'ClaveMala1')->assertStatus(401);
        $this->iniciarSesion($usuario->email, 'ClaveMala2')->assertStatus(401);
        $this->iniciarSesion($usuario->email, 'ClaveMala3')->assertStatus(423);

        $this->assertTrue((bool) $usuario->fresh()->is_locked, 'La cuenta debio quedar bloqueada.');

        // Con la clave correcta ya no debe permitir el ingreso.
        $this->iniciarSesion($usuario->email, 'Password123!')->assertStatus(423);
    }

    // Sesion unica: un nuevo login invalida el token anterior
    public function test_sesion_unica_el_token_anterior_deja_de_funcionar(): void
    {
        $usuario = $this->crearUsuario('usuario');

        $token1 = $this->iniciarSesion($usuario->email, 'Password123!')->json('data.token');
        $this->consultarMe($token1)->assertStatus(200);

        $token2 = $this->iniciarSesion($usuario->email, 'Password123!')->json('data.token');

        $this->consultarMe($token1)->assertStatus(401);
        $this->consultarMe($token2)->assertStatus(200);
    }

    // CP-19 (API)
    public function test_ruta_protegida_sin_token_devuelve_401(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/me')->assertStatus(401);
    }
}
