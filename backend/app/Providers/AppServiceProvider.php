<?php

namespace App\Providers;

use App\Contracts\OrganizationParser;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(OrganizationParser::class, YandexMapsParser::class);

        Model::preventLazyLoading(! app()->isProduction());

        $this->app->booted(function (): void {
            RateLimiter::for('login', function (Request $request): Limit {
                $email = Str::transliterate(Str::lower($request->string('email')->toString()));

                return Limit::perMinute(5)->by($email.'|'.$request->ip());
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
