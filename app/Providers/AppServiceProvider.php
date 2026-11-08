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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // MyISAM (InfinityFree) limita las claves a 1000 bytes: 191 caracteres x 4 bytes de utf8mb4 cabe
        Schema::defaultStringLength(191);

        // Tras el proxy de InfinityFree PHP puede ver HTTP aunque el visitante use HTTPS:
        // los enlaces y formularios generados deben salir siempre con https://
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

        $this->registrarLimitesDePeticiones();
    }

    /**
     * Un límite con nombre por acción. El `throttle:N,1` sin nombre de Laravel
     * comparte un único contador por usuario/IP entre todas las rutas, así que
     * el sondeo de estado de la ficha gastaba el cupo de pagos y el login el de
     * registro (un aula detrás de la misma IP recibía 429 al registrarse).
     */
    private function registrarLimitesDePeticiones(): void
    {
        $porMinuto = [
            'login' => 20,
            'registro' => 6,
            'recuperacion' => 6,
            'estado-inmueble' => 60,
            'reservas' => 20,
            'pagos' => 10,
            'tarjetas' => 10,
            'citas' => 20,
            'franjas' => 60,
            'mensajes' => 30,
            'contactar' => 20,
        ];

        foreach ($porMinuto as $nombre => $limite) {
            RateLimiter::for($nombre, fn (Request $peticion) => Limit::perMinute($limite)
                ->by((string) ($peticion->user()?->getAuthIdentifier() ?? $peticion->ip())));
        }
    }
}
