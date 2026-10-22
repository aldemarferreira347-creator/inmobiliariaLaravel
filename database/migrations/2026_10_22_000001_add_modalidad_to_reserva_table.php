<?php

use App\Enumerados\ModalidadInmueble;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La reserva recordaba solo el monto, no si era de compra o de arriendo.
     * Sin eso, una reserva de compra sobre un inmueble «ambos» podía recibir
     * un contrato de arriendo (con el precio de venta como renta mensual).
     */
    public function up(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            $table->enum('modalidad', [ModalidadInmueble::Venta->value, ModalidadInmueble::Arriendo->value])
                ->nullable()
                ->after('monto_reserva');
        });

        // Reservas previas: la modalidad del inmueble, o, si admite ambas, la
        // que coincide con el monto cobrado.
        DB::table('reserva')
            ->join('inmueble', 'inmueble.id', '=', 'reserva.inmueble_id')
            ->select('reserva.id', 'reserva.monto_reserva', 'inmueble.modalidad', 'inmueble.precio_venta')
            ->orderBy('reserva.id')
            ->each(function (object $fila) {
                $modalidad = $fila->modalidad === ModalidadInmueble::Ambos->value
                    ? ((float) $fila->monto_reserva === (float) $fila->precio_venta
                        ? ModalidadInmueble::Venta
                        : ModalidadInmueble::Arriendo)
                    : ModalidadInmueble::from($fila->modalidad);

                DB::table('reserva')->where('id', $fila->id)->update(['modalidad' => $modalidad->value]);
            });
    }

    public function down(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }
};
