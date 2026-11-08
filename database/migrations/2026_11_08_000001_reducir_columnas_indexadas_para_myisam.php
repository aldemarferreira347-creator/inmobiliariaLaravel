<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SQLite reconstruye la tabla con VACUUM al cambiar una columna indexada, y eso no admite transacción
    public $withinTransaction = false;

    /**
     * MyISAM (InfinityFree) limita cada clave a 1000 bytes y utf8mb4 usa 4 por
     * carácter. Estas dos columnas se declararon con 255 caracteres de forma
     * explícita (la longitud por defecto no las alcanza) y, junto a `pasarela`,
     * sus índices únicos pasaban de ese límite. Los identificadores de Stripe
     * (`evt_…`, `pm_…`) miden menos de 100 caracteres.
     */
    public function up(): void
    {
        Schema::table('webhook_evento', fn (Blueprint $t) => $t->string('evento_id', 191)->change());
        Schema::table('metodo_pago_guardado', fn (Blueprint $t) => $t->string('token_pasarela', 191)->change());
    }

    public function down(): void
    {
        Schema::table('webhook_evento', fn (Blueprint $t) => $t->string('evento_id', 255)->change());
        Schema::table('metodo_pago_guardado', fn (Blueprint $t) => $t->string('token_pasarela', 255)->change());
    }
};
