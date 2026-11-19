<?php

namespace Tests\Feature;

use Tests\Support\CreaDatos;
use Tests\TestCase;

/**
 * CP-13, CP-14, ST-09 y regla RN-008: administracion de usuarios y roles (CU-007).
 */
class UsuariosRolesTest extends TestCase
{
    use CreaDatos;

    // CP-13
    public function test_el_administrador_cambia_el_rol_de_otro_usuario(): void
    {
        $this->actuarComo('admin');
        $otro = $this->crearUsuario('usuario');

        $r = $this->patchJson("/api/v1/usuarios/{$otro->id}/rol", ['rol' => 'vocero']);

        $r->assertStatus(200);
        $this->assertSame('vocero', $otro->fresh()->rol);
    }

    // CP-13 / ST-09: los tokens del usuario se revocan
    public function test_al_cambiar_el_rol_se_revocan_los_tokens_del_usuario(): void
    {
        $this->actuarComo('admin');
        $otro  = $this->crearUsuario('usuario');
        $token = $otro->createToken('prueba')->plainTextToken;
        $this->assertSame(1, $otro->tokens()->count());

        $this->patchJson("/api/v1/usuarios/{$otro->id}/rol", ['rol' => 'vocero'])->assertStatus(200);

        $this->assertSame(0, $otro->tokens()->count(), 'Los tokens debieron revocarse.');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me', ['Authorization' => 'Bearer ' . $token])->assertStatus(401);
    }

    // CP-14 / RN-008
    public function test_el_administrador_no_puede_cambiar_su_propio_rol(): void
    {
        $admin = $this->actuarComo('admin');

        $r = $this->patchJson("/api/v1/usuarios/{$admin->id}/rol", ['rol' => 'usuario']);

        $r->assertStatus(422);
        $this->assertSame('admin', $admin->fresh()->rol);
    }

    // CP-14 / RN-008
    public function test_el_administrador_no_puede_desactivarse_a_si_mismo(): void
    {
        $admin = $this->actuarComo('admin');

        $r = $this->patchJson("/api/v1/usuarios/{$admin->id}/estado", ['activo' => false]);

        $r->assertStatus(422);
        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_rol_invalido_es_rechazado(): void
    {
        $this->actuarComo('admin');
        $otro = $this->crearUsuario('usuario');

        $r = $this->patchJson("/api/v1/usuarios/{$otro->id}/rol", ['rol' => 'superadmin']);

        $r->assertStatus(422);
        $r->assertJsonValidationErrors(['rol']);
        $this->assertSame('usuario', $otro->fresh()->rol);
    }

    public function test_el_administrador_puede_desactivar_y_reactivar_una_cuenta(): void
    {
        $this->actuarComo('admin');
        $otro = $this->crearUsuario('usuario');

        $this->patchJson("/api/v1/usuarios/{$otro->id}/estado", ['activo' => false])->assertStatus(200);
        $this->assertNotNull($otro->fresh()->deleted_at ?? \App\Models\User::withTrashed()->find($otro->id)->deleted_at);

        $this->patchJson("/api/v1/usuarios/{$otro->id}/estado", ['activo' => true])->assertStatus(200);
        $this->assertNull(\App\Models\User::withTrashed()->find($otro->id)->deleted_at);
    }

    public function test_el_administrador_puede_desbloquear_una_cuenta(): void
    {
        $this->actuarComo('admin');
        $bloqueado = $this->crearUsuario('usuario', ['is_locked' => true, 'failed_attempts' => 3]);

        $this->patchJson("/api/v1/usuarios/{$bloqueado->id}/desbloquear")->assertStatus(200);

        $this->assertFalse((bool) $bloqueado->fresh()->is_locked);
    }

    public function test_vocero_y_ciudadano_no_pueden_cambiar_roles(): void
    {
        $objetivo = $this->crearUsuario('usuario');

        $this->actuarComo('vocero');
        $this->patchJson("/api/v1/usuarios/{$objetivo->id}/rol", ['rol' => 'admin'])->assertStatus(403);

        $this->actuarComo('usuario');
        $this->patchJson("/api/v1/usuarios/{$objetivo->id}/rol", ['rol' => 'admin'])->assertStatus(403);

        $this->assertSame('usuario', $objetivo->fresh()->rol);
    }
}
