<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

/**
 * Roles del sistema (tabla `rol`).
 * El código coincide con `usuario.rol`, que es FK contra `rol.codigo`.
 */
enum RolUsuario: string
{
    use ConValores;

    case Administrador = 'administrador';
    case Asesor = 'asesor';
    case Cliente = 'cliente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Asesor => 'Asesor',
            self::Cliente => 'Cliente',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Administrador => 'Gestiona inmuebles, contratos, usuarios, asesores, roles y reportes.',
            self::Asesor => 'Gestiona citas asignadas, registra observaciones, responde mensajes y gestiona ventas.',
            self::Cliente => 'Usuario externo registrado: consulta inmuebles, agenda citas, reserva, paga, mensajea.',
        };
    }

    // Plural correcto para los rótulos de las tarjetas de resumen
    public function etiquetaPlural(): string
    {
        return match ($this) {
            self::Administrador => 'Administradores',
            self::Asesor => 'Asesores',
            self::Cliente => 'Clientes',
        };
    }

    // Clase CSS del distintivo de rol en las tablas del panel
    public function claseBadge(): string
    {
        return match ($this) {
            self::Administrador => 'badge-rol-admin',
            self::Asesor => 'badge-rol-asesor',
            self::Cliente => 'badge-rol-cliente',
        };
    }

    // Ruta a la que se redirige tras iniciar sesión (HU-05.1)
    public function rutaInicio(): string
    {
        return match ($this) {
            self::Administrador => 'admin.inmuebles.index',
            self::Asesor => 'asesor.ventas.index',
            self::Cliente => 'inicio',
        };
    }

    // Los paneles con sidebar solo aplican a roles internos
    public function usaPanel(): bool
    {
        return $this !== self::Cliente;
    }
}
