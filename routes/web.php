<?php

use App\Http\Controllers\Admin\CitaController as AdminCitaController;
use App\Http\Controllers\Admin\ContratoController;
use App\Http\Controllers\Admin\FranjaController;
use App\Http\Controllers\Admin\ImagenInmuebleController;
use App\Http\Controllers\Admin\InmuebleController as AdminInmuebleController;
use App\Http\Controllers\Admin\NotificacionController as AdminNotificacionController;
use App\Http\Controllers\Admin\PermisoController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\ReservaController as AdminReservaController;
use App\Http\Controllers\Admin\UsuarioController;
use App\Http\Controllers\Asesor\CitaController as AsesorCitaController;
use App\Http\Controllers\Asesor\VentaController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\DescargaController;
use App\Http\Controllers\FavoritoController;
use App\Http\Controllers\InmuebleController;
use App\Http\Controllers\MensajeController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Mapa general de rutas web de la aplicación:
 *  - Webhook de Stripe (pública, validada por firma, no por sesión).
 *  - Catálogo público de inmuebles (sin autenticación).
 *  - Área del cliente autenticado: perfil, favoritos, reservas, tarjetas,
 *    citas, mensajería y notificaciones.
 *  - Panel del asesor ('/asesor'): ventas y sus citas asignadas.
 *  - Panel de administración ('/admin'): inmuebles, usuarios, citas,
 *    franjas, reservas, contratos, notificaciones y reportes.
 * Las rutas de autenticación (login, registro, contraseña) están en auth.php.
 */

// HU-20.4 / HU-23.1: Stripe llama a esta ruta directamente; se valida por
// firma (Stripe-Signature), no por sesión, y por eso vive fuera de los
// grupos de abajo y está exenta de CSRF (ver bootstrap/app.php).
Route::post('stripe/webhook', [StripeWebhookController::class, 'recibir'])->name('stripe.webhook');

/*
 * Catálogo público (HU-01 / HU-02)
 */
Route::get('/', [InmuebleController::class, 'inicio'])->name('inicio');
Route::get('inmuebles', [InmuebleController::class, 'index'])->name('inmuebles.index');
Route::get('inmuebles/{inmueble}', [InmuebleController::class, 'show'])->name('inmuebles.show');

/*
 * Área del usuario autenticado (HU-18 / HU-25)
 */
Route::middleware('auth')->group(function () {
    Route::get('perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::patch('perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::post('perfil/foto', [PerfilController::class, 'actualizarFoto'])->name('perfil.foto.store');
    Route::delete('perfil/foto', [PerfilController::class, 'eliminarFoto'])->name('perfil.foto.destroy');

    Route::get('favoritos', [FavoritoController::class, 'index'])->name('favoritos.index');
    Route::post('favoritos/{inmueble}', [FavoritoController::class, 'toggle'])->name('favoritos.toggle');

    // Reservas del cliente (HU-07 / HU-23)
    Route::get('mis-reservas', [ReservaController::class, 'index'])->name('reservas.index');
    Route::post('reservas', [ReservaController::class, 'store'])->name('reservas.store');
    Route::get('reservas/{reserva}', [ReservaController::class, 'show'])->name('reservas.show');
    Route::post('reservas/{reserva}/pago', [ReservaController::class, 'registrarPago'])->name('reservas.pago');
    Route::post('reservas/{reserva}/pagar-con-tarjeta/{tarjeta}', [ReservaController::class, 'pagarConTarjeta'])->name('reservas.pagar-con-tarjeta');
    Route::post('reservas/{reserva}/cancelar', [ReservaController::class, 'cancelar'])->name('reservas.cancelar');

    // Tarjetas guardadas del cliente (HU-20)
    Route::get('perfil/tarjetas', [MetodoPagoController::class, 'index'])->name('perfil.tarjetas.index');
    Route::post('perfil/tarjetas/setup-intent', [MetodoPagoController::class, 'setupIntent'])->name('perfil.tarjetas.setup-intent');
    Route::post('perfil/tarjetas', [MetodoPagoController::class, 'store'])->name('perfil.tarjetas.store');
    Route::delete('perfil/tarjetas/{tarjeta}', [MetodoPagoController::class, 'destroy'])->name('perfil.tarjetas.destroy');

    // Historial del cliente (HU-19)
    Route::get('mis-arriendos', [PerfilController::class, 'misArriendos'])->name('perfil.arriendos');
    Route::get('mis-compras', [PerfilController::class, 'misCompras'])->name('perfil.compras');

    // Citas de visita del cliente (HU-27)
    Route::get('mis-citas', [CitaController::class, 'index'])->name('citas.index');
    Route::post('citas', [CitaController::class, 'store'])->name('citas.store');
    Route::post('citas/{cita}/cancelar', [CitaController::class, 'cancelar'])->name('citas.cancelar');
    Route::get('citas/franjas-disponibles', [CitaController::class, 'franjasDisponibles'])->name('citas.franjas-disponibles');

    // Documentos privados: contratos firmados y escrituras
    Route::get('contratos/{contrato}/descargar', [DescargaController::class, 'contrato'])->name('contratos.descargar');
    Route::get('ventas/{venta}/escritura', [DescargaController::class, 'escritura'])->name('ventas.escritura');

    // Notificaciones (HU-15)
    Route::get('notificaciones', [NotificacionController::class, 'index'])->name('notificaciones.index');
    Route::patch('notificaciones/leidas', [NotificacionController::class, 'marcarTodas'])->name('notificaciones.leidas');
    Route::patch('notificaciones/{notificacion}', [NotificacionController::class, 'marcarLeida'])->name('notificaciones.leida');

    // Mensajería (HU-13)
    Route::get('mensajes', [MensajeController::class, 'index'])->name('mensajes.index');
    Route::get('mensajes/sin-leer', [MensajeController::class, 'sinLeer'])->name('mensajes.sin-leer');
    Route::get('mensajes/{conversacion}', [MensajeController::class, 'show'])->name('mensajes.show');
    Route::post('mensajes/{conversacion}', [MensajeController::class, 'store'])->name('mensajes.store');
    Route::get('mensajes/{conversacion}/anteriores', [MensajeController::class, 'anteriores'])->name('mensajes.anteriores');
    Route::get('mensajes/{conversacion}/nuevos', [MensajeController::class, 'nuevos'])->name('mensajes.nuevos');
    Route::post('inmuebles/{inmueble}/contactar', [MensajeController::class, 'iniciar'])->name('mensajes.iniciar');
});

/*
 * Ventas (HU-14). Las lleva el asesor y las supervisa el administrador.
 * El rol se comprueba con el middleware 'rol' de cada subgrupo (ventas admite
 * asesor+administrador, citas de agenda solo asesor).
 */
Route::middleware('auth')
    ->prefix('asesor')
    ->name('asesor.')
    ->group(function () {
        Route::middleware('rol:asesor,administrador')->group(function () {
            Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
            Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
            Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
            Route::post('ventas/{venta}/cerrar', [VentaController::class, 'cerrar'])->name('ventas.cerrar');
            Route::post('ventas/{venta}/cancelar', [VentaController::class, 'cancelar'])->name('ventas.cancelar');
            Route::post('ventas/{venta}/escritura', [VentaController::class, 'subirEscritura'])->name('ventas.escritura');
        });

        // Citas asignadas al asesor (HU-11 / HU-12)
        Route::middleware('rol:asesor')->group(function () {
            Route::get('citas', [AsesorCitaController::class, 'index'])->name('citas.index');
            Route::get('citas/{cita}', [AsesorCitaController::class, 'show'])->name('citas.show');
            Route::post('citas/{cita}/observacion', [AsesorCitaController::class, 'registrarObservacion'])->name('citas.observacion');
            Route::patch('citas/{cita}/observacion', [AsesorCitaController::class, 'editarObservacion'])->name('citas.observacion.editar');
        });
    });

/*
 * Panel de administración (HU-04 / HU-08 / HU-16 / HU-26).
 * El middleware 'rol:administrador' protege todo el grupo.
 */
Route::middleware(['auth', 'rol:administrador'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // El alta y la edición se hacen desde modales del listado (HU-04.1/04.2),
        // por eso no hay pantallas `create` ni `edit` aparte.
        Route::resource('inmuebles', AdminInmuebleController::class)->except(['create', 'edit']);

        Route::patch('imagenes/{imagen}/principal', [ImagenInmuebleController::class, 'principal'])
            ->name('imagenes.principal');
        Route::delete('imagenes/{imagen}', [ImagenInmuebleController::class, 'destroy'])
            ->name('imagenes.destroy');

        Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuario.store');
        Route::patch('usuarios/{usuario}/rol', [UsuarioController::class, 'cambiarRol'])->name('usuarios.rol');
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'cambiarEstado'])->name('usuarios.estado');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

        Route::get('permisos', [PermisoController::class, 'index'])->name('permisos.index');

        // Citas de visita (HU-10) y franjas horarias (RF-26.2)
        Route::get('citas', [AdminCitaController::class, 'index'])->name('citas.index');
        Route::get('citas/{cita}', [AdminCitaController::class, 'show'])->name('citas.show');
        Route::post('citas/{cita}/asignar', [AdminCitaController::class, 'asignar'])->name('citas.asignar');
        Route::get('franjas', [FranjaController::class, 'index'])->name('franjas.index');
        Route::post('franjas', [FranjaController::class, 'update'])->name('franjas.update');

        Route::get('reservas', [AdminReservaController::class, 'index'])->name('reservas.index');
        Route::get('reservas/{reserva}', [AdminReservaController::class, 'show'])->name('reservas.show');
        Route::post('reservas/{reserva}/pagos/{pago}', [AdminReservaController::class, 'revisarPago'])->name('reservas.pagos.revisar');
        Route::post('reservas/{reserva}/cancelar', [AdminReservaController::class, 'cancelar'])->name('reservas.cancelar');

        Route::get('contratos', [ContratoController::class, 'index'])->name('contratos.index');
        Route::get('contratos/nuevo', [ContratoController::class, 'create'])->name('contratos.create');
        Route::post('contratos', [ContratoController::class, 'store'])->name('contratos.store');
        Route::get('contratos/{contrato}', [ContratoController::class, 'show'])->name('contratos.show');
        Route::post('contratos/{contrato}/documento', [ContratoController::class, 'subirDocumento'])->name('contratos.documento');
        Route::post('contratos/{contrato}/rescindir', [ContratoController::class, 'rescindir'])->name('contratos.rescindir');

        Route::get('notificaciones', [AdminNotificacionController::class, 'create'])->name('notificaciones.create');
        Route::post('notificaciones', [AdminNotificacionController::class, 'store'])->name('notificaciones.store');

        // Reportes (HU-06 / HU-21). {tipo} se resuelve al enum TipoReporte.
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('reportes/{tipo}', [ReporteController::class, 'show'])->name('reportes.show');
        Route::get('reportes/{tipo}/excel', [ReporteController::class, 'excel'])->name('reportes.excel');
        Route::get('reportes/{tipo}/pdf', [ReporteController::class, 'pdf'])->name('reportes.pdf');
    });

require __DIR__.'/auth.php';
