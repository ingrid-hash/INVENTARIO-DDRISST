<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // El Superadmin tiene acceso total sin necesidad de que se le asignen
        // los permisos uno por uno.
        Gate::before(fn (User $usuario, string $ability) => $usuario->esSuperadmin() ? true : null);

        // En produccion todo el trafico debe ir por HTTPS: asi las cookies de
        // sesion nunca viajan en claro.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
