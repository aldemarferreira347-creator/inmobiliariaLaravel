<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Tareas programadas (HU-09.2 / HU-17.3).
 * Requieren un único cron en el servidor:
 *   * * * * * php /ruta/al/proyecto/artisan schedule:run >> /dev/null 2>&1
 */

Schedule::command('reservas:expirar')->hourly()->withoutOverlapping();
Schedule::command('contratos:vencer')->hourly()->withoutOverlapping();
