<?php

namespace App\Providers;

use App\Models\Cita;
use App\Models\Contrato;
use App\Models\Conversacion;
use App\Models\Inmueble;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Politicas\CitaPolicy;
use App\Politicas\ContratoPolicy;
use App\Politicas\ConversacionPolicy;
use App\Politicas\InmueblePolicy;
use App\Politicas\ReservaPolicy;
use App\Politicas\UserPolicy;
use App\Politicas\VentaPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // La paginación usa la plantilla propia del sistema, no la de Tailwind por defecto
        Paginator::defaultView('vendor.pagination.default');
        Paginator::defaultSimpleView('vendor.pagination.default');

        // Las policies viven en App\Politicas, fuera de la convención App\Policies
        // que Laravel resuelve automáticamente, así que se registran a mano.
        Gate::policy(Cita::class, CitaPolicy::class);
        Gate::policy(Contrato::class, ContratoPolicy::class);
        Gate::policy(Conversacion::class, ConversacionPolicy::class);
        Gate::policy(Inmueble::class, InmueblePolicy::class);
        Gate::policy(Reserva::class, ReservaPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Venta::class, VentaPolicy::class);
    }
}
