<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `inmueble.precio_venta` admite hasta 15 dígitos, pero el monto de la
     * reserva, el del pago y el precio de la venta que lo copian solo 12: un
     * inmueble caro se podía publicar y luego no reservar ni vender.
     */
    public function up(): void
    {
        Schema::table('reserva', fn (Blueprint $t) => $t->decimal('monto_reserva', 15, 2)->default(0)->change());
        Schema::table('pago', fn (Blueprint $t) => $t->decimal('monto', 15, 2)->change());
        Schema::table('venta', fn (Blueprint $t) => $t->decimal('precio_venta', 15, 2)->change());
    }

    public function down(): void
    {
        Schema::table('reserva', fn (Blueprint $t) => $t->decimal('monto_reserva', 12, 2)->default(0)->change());
        Schema::table('pago', fn (Blueprint $t) => $t->decimal('monto', 12, 2)->change());
        Schema::table('venta', fn (Blueprint $t) => $t->decimal('precio_venta', 12, 2)->change());
    }
};
