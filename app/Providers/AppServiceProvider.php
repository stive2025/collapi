<?php

namespace App\Providers;

use App\Mail\Transport\GraphMailTransport;
use Illuminate\Support\Facades\Mail;
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
        Mail::extend('graph', function () {
            return new GraphMailTransport();
        });
    }
}
