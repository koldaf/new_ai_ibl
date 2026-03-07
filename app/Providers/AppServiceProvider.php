<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // if the HTTP kernel is missing (this project appears to have removed
        // the standard `app/Http/Kernel.php`), we can still register route
        // middleware aliases directly on the router. the `role` alias is used
        // in routes/web.php.
        $this->app->make(\Illuminate\Routing\Router::class)
            ->aliasMiddleware('role', \App\Http\Middleware\RoleMiddleware::class);
    }
}
