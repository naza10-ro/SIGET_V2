<?php

namespace Tests\Support;

use App\Models\Calle;
use App\Models\Encuesta;
use App\Models\CuentaPago;
use App\Models\Familia;
use App\Models\Habitante;
use App\Models\PagoQr;
use App\Models\Sede;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

/**
 * Ayudas para crear datos de prueba en la base siget_test.
 */
trait CreaDatos
{
    protected int $contadorDatos = 0;

    protected function siguiente(): int
    {
        return ++$this->contadorDatos;
    }

    protected function crearUsuario(string $rol = 'usuario', array $extra = []): User
    {
        $n = $this->siguiente();

        return User::factory()->create(array_merge([
            'name'     => "Usuario Prueba {$n}",
            'email'    => "prueba{$n}_" . uniqid() . '@siget.test',
            'password' => Hash::make('Password123!'),
            'rol'      => $rol,
        ], $extra));
    }

    /** Inicia sesión (sin pasar por /login) como un usuario del rol indicado. */
    protected function actuarComo(string $rol): User
    {
        $usuario = $this->crearUsuario($rol);
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($usuario, ['*']);

        return $usuario;
    }

    protected function crearSede(): Sede
    {
        $n = $this->siguiente();

        return Sede::forceCreate([
            'nombre'                 => "Sede de prueba {$n}",
            'direccion'              => 'Direccion de prueba',
            'tipo_sede'              => 'administrativa',
            'estado_infraestructura' => 'regular',
        ]);
    }

    protected function crearCalle(Sede $sede): Calle
    {
        $n = $this->siguiente();

        return Calle::forceCreate([
            'sede_id'  => $sede->id,
            'nombre'   => "Calle de prueba {$n}",
            'latitud'  => 10.3412000,
            'longitud' => -67.0421000,
        ]);
    }

    protected function crearFamilia(?Sede $sede = null, ?Calle $calle = null): Familia
    {
        $n = $this->siguiente();

        return Familia::forceCreate([
            'sede_id'            => $sede?->id,
            'calle_id'           => $calle?->id,
            'codigo_vivienda'    => "T-{$n}-" . uniqid(),
            'nombre_responsable' => 'Responsable de prueba',
            'condicion_vivienda' => 'regular',
        ]);
    }

    protected function crearHabitante(Familia $familia): Habitante
    {
        $n = $this->siguiente();

        return Habitante::forceCreate([
            'familia_id'       => $familia->id,
            'cedula'           => 'V-' . (10000000 + $n),
            'nombres'          => 'Nombre',
            'apellidos'        => 'Apellido',
            'fecha_nacimiento' => '1990-01-01',
            'sexo'             => 'femenino',
            'nivel_educativo'  => 'ninguno',
        ]);
    }

    protected function crearCuentaPago(): CuentaPago
    {
        return CuentaPago::forceCreate([
            'nombre_servicio' => 'Gas',
            'metodo'          => 'pago_movil',
            'banco'           => 'Banco de Venezuela (0102)',
            'telefono'        => '04141234567',
            'cedula_rif'      => 'V-12345678',
            'titular'         => 'Titular de prueba',
            'activa'          => true,
        ]);
    }

    protected function crearPagoPendiente(): PagoQr
    {
        $n = $this->siguiente();
        $cuenta = $this->crearCuentaPago();

        return PagoQr::forceCreate([
            'cuenta_pago_id'      => $cuenta->id,
            'referencia_bancaria' => 'REF' . $n . uniqid(),
            'monto'               => 15.00,
            'concepto_pago'       => 'Cuota de prueba',
            'metodo_pago'         => 'pago_movil',
            'estado'              => 'pendiente',
            'fecha_pago'          => now(),
        ]);
    }

    protected function crearEncuestaGas(): Encuesta
    {
        return Encuesta::forceCreate([
            'nombre' => 'Censo de gas de prueba',
            'tipo'   => Encuesta::TIPO_GAS,
            'activa' => true,
        ]);
    }

    /** Imagen PNG valida de 1x1 pixel para usar como comprobante de pago. */
    protected function comprobantePng(string $nombre = 'comprobante.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($nombre, $png);
    }
}
