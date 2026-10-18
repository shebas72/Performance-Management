<?php

namespace App\Saas;

use App\Models\Company;
use App\Saas\Filament\SuperAdminPanelProvider;
use App\Saas\Models\Subscription;
use App\Saas\Services\PlanLimits;
use App\Services\Limits;
use App\Support\AppMode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Everything that only exists in the SaaS product hangs off this provider: plan enforcement, the super-admin
 * panel, tenant sign-up, the plan endpoint, and the plans / subscriptions / invoices tables.
 *
 * It does nothing unless APP_MODE=saas, so the same repo can be run as either product while developing.
 * A standalone build deletes app/Saas; bootstrap/providers.php skips this provider when the class is missing.
 */
class SaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (! AppMode::saas()) {
            return;
        }

        // One PlanLimits per request, shared by everything that asks for the generic Limits class.
        $this->app->scoped(PlanLimits::class);
        $this->app->bind(Limits::class, fn ($app) => $app->make(PlanLimits::class));

        // The super-admin panel (/super-admin) exists only in SaaS.
        $this->app->register(SuperAdminPanelProvider::class);
    }

    public function boot(): void
    {
        if (! AppMode::saas()) {
            return;
        }

                // SMTP saved in Platform > Settings overrides the .env mail config.
        \App\Saas\Services\MailSettings::apply();

        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        // Sign-in is refused until the tenant's email is verified and the super admin approved the organisation.
        \App\Support\LoginGate::register(fn (\App\Models\User $user) => \App\Saas\Services\TenantSignup::loginBlock($user));

        // Company->subscription() lives here so the shared Company model never mentions SaaS classes.
        Company::resolveRelationUsing('subscription', fn (Company $company) => $company->hasOne(Subscription::class)->latestOfMany());

                if ($this->app->runningInConsole()) {
            $this->commands([\App\Saas\Console\ExpireSubscriptions::class]);
        }

        if (! $this->app->routesAreCached()) {
            Route::middleware('api')->prefix('api')->group(__DIR__.'/routes/api.php');
        }
    }
}
