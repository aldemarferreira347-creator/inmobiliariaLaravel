<?php

use App\Enumerados\EstadoUsuario;
use App\Enumerados\RolUsuario;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, permisos y usuarios.
 *
 * HU-03 / HU-05 / HU-16 / HU-24 / HU-25 / HU-26:
 * registro, autenticación, gestión de perfil y administración de roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rol', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 50);
            $table->string('descripcion', 255)->nullable();
        });

        // RF-25.4 / CU-20: catálogo de permisos por rol, de solo lectura
        Schema::create('permiso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rol_id')->constrained('rol')->cascadeOnDelete();
            $table->string('modulo', 50);
            $table->enum('accion', ['create', 'read', 'update', 'delete']);
            $table->boolean('activo')->default(true);

            $table->unique(['rol_id', 'modulo', 'accion']);
        });

        Schema::create('usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('email', 150)->unique();
            $table->string('contrasena');
            $table->string('telefono', 20)->nullable();
            $table->string('rol', 20)->default(RolUsuario::Cliente->value);
            $table->string('documento_tipo', 20)->nullable();
            $table->string('documento_numero', 30)->nullable()->unique();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('direccion')->nullable();
            $table->string('foto_url')->nullable();
            $table->enum('estado', EstadoUsuario::valores())->default(EstadoUsuario::Activo->value);
            $table->timestamp('desactivado_en')->nullable();
            $table->foreignId('desactivado_por')->nullable()->constrained('usuario')->nullOnDelete();
            $table->rememberToken();
            $table->timestamp('creado_en')->useCurrent();

            $table->foreign('rol')->references('codigo')->on('rol')->cascadeOnUpdate();
            $table->index('rol');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('usuario');
        Schema::dropIfExists('permiso');
        Schema::dropIfExists('rol');
    }
};
