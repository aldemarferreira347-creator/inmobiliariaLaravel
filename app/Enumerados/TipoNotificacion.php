<?php

namespace App\Enumerados;

use App\Enumerados\Concerns\ConValores;

// Severidad de una notificación in-app (HU-15 / HU-22)
enum TipoNotificacion: string
{
    use ConValores;

    case Info = 'info';
    case Exito = 'success';
    case Aviso = 'warning';
    case Error = 'error';
    case Sistema = 'sistema';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Info => 'Información',
            self::Exito => 'Éxito',
            self::Aviso => 'Aviso',
            self::Error => 'Error',
            self::Sistema => 'Sistema',
        };
    }

    public function icono(): string
    {
        return match ($this) {
            self::Exito => 'circle-check',
            self::Aviso => 'triangle-alert',
            self::Error => 'circle-x',
            self::Info, self::Sistema => 'bell',
        };
    }

    // Sufijo de las clases .notif-tipo-* del diseño
    public function claseCss(): string
    {
        return match ($this) {
            self::Exito => 'notif-tipo-success',
            self::Aviso => 'notif-tipo-warning',
            self::Error => 'notif-tipo-error',
            self::Info, self::Sistema => '',
        };
    }
}
