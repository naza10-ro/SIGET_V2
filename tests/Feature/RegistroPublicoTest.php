<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * ST-12: la ruta publica de registro no debe permitir crear cuentas con privilegios.
 */
class RegistroPublicoTest extends TestCase
{
    public function test_el_registro_publico_no_permite_elegir_el_rol(): void
    {
        $this->app['auth']->forgetGuards();

        $r = $this->postJson('/api/v1/register', [
            'name'                  => 'Intruso de prueba',
            'cedula'                => 'V-99999999',
            'email'                 => 'intruso@siget.test',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'rol'                   => 'admin',
        ]);

        $this->assertContains($r->getStatusCode(), [200, 201, 422], 'Respuesta inesperada: ' . $r->getContent());
        $this->assertDatabaseMissing('users', ['email' => 'intruso@siget.test', 'rol' => 'admin']);
    }

    public function test_el_registro_publico_valida_los_datos(): void
    {
        $this->app['auth']->forgetGuards();

        $r = $this->postJson('/api/v1/register', ['email' => 'no-es-un-correo', 'password' => '123']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['name', 'cedula', 'email', 'password']);
    }
}
