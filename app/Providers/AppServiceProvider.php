<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        Paginator::useBootstrapFive();

        Gate::define('client', function(User $user) {
            return $user->user_type == 'client';
        });

        Gate::define('admin', function(User $user) {
            return $user->user_type == 'admin';
        });

        Gate::define('technician', function(User $user) {
            return $user->user_type == 'technician';
        });

        Gate::define('cashier', function(User $user) {
            return $user->user_type == 'cashier';
        });

    }
}
