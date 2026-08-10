<?php

namespace Database\Seeders;

use App\Enumerados\EstadoInmueble;
use App\Enumerados\EstadoPago;
use App\Enumerados\EstadoReserva;
use App\Enumerados\MetodoPago;
use App\Enumerados\RolUsuario;
use App\Enumerados\TipoNotificacion;
use App\Models\Contrato;
use App\Models\Conversacion;
use App\Models\HistorialReserva;
use App\Models\Inmueble;
use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;

/**
 * Operaciones de demostración: una reserva de cada tipo, un contrato vigente,
 * una venta, una conversación y algunas notificaciones. Sirve para recorrer
 * todas las pantallas sin tener que crear los datos a mano.
 */
class OperacionSeeder extends Seeder
{
    public function run(): void
    {
        $cliente = User::delRol(RolUsuario::Cliente)->firstOrFail();
        $asesor = User::delRol(RolUsuario::Asesor)->firstOrFail();
        $inmuebles = Inmueble::orderBy('id')->get();

        $this->reservaPendiente($cliente, $inmuebles->get(0));
        $this->reservaConContrato($cliente, $inmuebles->get(1));
        $this->venta($cliente, $asesor, $inmuebles->get(2));
        $this->conversacion($cliente, $asesor, $inmuebles->get(0));
        $this->notificaciones($cliente);
    }

    // Reserva recién solicitada: el inmueble sigue disponible hasta que se confirme el pago
    private function reservaPendiente(User $cliente, Inmueble $inmueble): void
    {
        $reserva = Reserva::create([
            'codigo_reserva' => Reserva::generarCodigo(),
            'inmueble_id' => $inmueble->id,
            'usuario_id' => $cliente->id,
            'monto_reserva' => $inmueble->precio_arrendamiento ?? $inmueble->precio_venta ?? 1_200_000,
            'estado' => EstadoReserva::PendientePago,
            'expira_en' => now()->addHours(Reserva::HORAS_PARA_PAGAR),
            'notas_cliente' => 'Me interesa visitarlo el fin de semana.',
        ]);

        HistorialReserva::registrar($reserva, null, EstadoReserva::PendientePago->value, 'Reserva creada por el cliente.', $cliente->id);

        Pago::create([
            'reserva_id' => $reserva->id,
            'metodo_pago' => MetodoPago::Transferencia,
            'monto' => $reserva->monto_reserva,
            'referencia' => 'REF-90210042',
            'estado' => EstadoPago::Procesando,
        ]);
    }

    // Reserva confirmada con contrato vigente: el inmueble queda ocupado
    private function reservaConContrato(User $cliente, Inmueble $inmueble): void
    {
        $reserva = Reserva::create([
            'codigo_reserva' => Reserva::generarCodigo(),
            'inmueble_id' => $inmueble->id,
            'usuario_id' => $cliente->id,
            'monto_reserva' => $inmueble->precio_arrendamiento ?? 1_500_000,
            'estado' => EstadoReserva::Confirmada,
            'expira_en' => now()->subDays(3),
        ]);

        HistorialReserva::registrar($reserva, null, EstadoReserva::PendientePago->value, 'Reserva creada por el cliente.', $cliente->id);
        HistorialReserva::registrar($reserva, EstadoReserva::PendientePago->value, EstadoReserva::Confirmada->value, 'Pago aprobado por la administración.');

        Pago::create([
            'reserva_id' => $reserva->id,
            'metodo_pago' => MetodoPago::Consignacion,
            'monto' => $reserva->monto_reserva,
            'referencia' => 'REF-77120018',
            'estado' => EstadoPago::Pagado,
            'revisado_en' => now()->subDays(2),
        ]);

        Contrato::create([
            'reserva_id' => $reserva->id,
            'numero_contrato' => Contrato::generarNumero($reserva),
            'fecha_inicio' => now()->subMonth(),
            'fecha_fin' => now()->addYear(),
            'valor_mensual' => $reserva->monto_reserva,
        ]);

        $inmueble->update(['estado' => EstadoInmueble::Ocupado]);
    }

    private function venta(User $cliente, User $asesor, Inmueble $inmueble): void
    {
        Venta::create([
            'inmueble_id' => $inmueble->id,
            'usuario_id' => $cliente->id,
            'asesor_id' => $asesor->id,
            'precio_venta' => $inmueble->precio_venta ?? 250_000_000,
            'fecha_venta' => now()->subWeeks(2),
            'notaria' => 'Notaría 2 de Neiva',
        ]);

        $inmueble->update(['estado' => EstadoInmueble::Reservado]);
    }

    private function conversacion(User $cliente, User $asesor, Inmueble $inmueble): void
    {
        $conversacion = Conversacion::create([
            'cliente_id' => $cliente->id,
            'asesor_id' => $asesor->id,
            'inmueble_id' => $inmueble->id,
            'ultimo_mensaje_en' => now(),
        ]);

        Mensaje::create([
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $cliente->id,
            'contenido' => "Hola, solicito información del inmueble con código {$inmueble->codigo}.",
            'leido_en' => now(),
            'creado_en' => now()->subMinutes(20),
        ]);

        Mensaje::create([
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $asesor->id,
            'contenido' => 'Claro que sí. ¿Te viene bien una visita el sábado por la mañana?',
            'creado_en' => now()->subMinutes(5),
        ]);
    }

    private function notificaciones(User $cliente): void
    {
        Notificacion::create([
            'usuario_id' => $cliente->id,
            'titulo' => 'Reserva registrada',
            'mensaje' => 'Tu reserva quedó registrada. Tienes 24 horas para completar el pago.',
            'tipo' => TipoNotificacion::Info,
            'referencia_tipo' => 'reserva',
            'referencia_id' => Reserva::where('usuario_id', $cliente->id)->value('id'),
        ]);

        Notificacion::create([
            'usuario_id' => $cliente->id,
            'titulo' => 'Bienvenido a Inmobiliaria García',
            'mensaje' => 'Gracias por registrarte. Guarda tus inmuebles favoritos para no perderlos de vista.',
            'tipo' => TipoNotificacion::Sistema,
            'leida_en' => now()->subDay(),
            'creado_en' => now()->subDays(2),
        ]);
    }
}
