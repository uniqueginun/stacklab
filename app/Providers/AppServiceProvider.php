<?php

namespace App\Providers;

use App\Operations\Aftermath\FinalizeSiteAftermath;
use App\Operations\Aftermath\PersistServerInfoAftermath;
use App\Operations\Aftermath\StepAftermathRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->tag([
            PersistServerInfoAftermath::class,
            FinalizeSiteAftermath::class,
        ], 'operation.step_aftermaths');

        $this->app->singleton(StepAftermathRegistry::class, function ($app): StepAftermathRegistry {
            return new StepAftermathRegistry($app->tagged('operation.step_aftermaths'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureHttpClient();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureHttpClient(): void
    {
        Http::macro('github', function (string $token): PendingRequest {
            return Http::baseUrl('https://api.github.com')
                ->withToken($token)
                ->acceptJson()
                ->withHeaders([
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->timeout(10)
                ->connectTimeout(3);
        });
    }
}
