# app/Saas: everything that exists only in the SaaS product

One repo while developing. The SaaS product is `APP_MODE=saas`; a standalone build is this repo with the
folders below deleted. Shared code never imports from here (see "The rule").

## What lives here

| Path | What it is |
|---|---|
| `SaasServiceProvider.php` | Wires everything below; does nothing unless `APP_MODE=saas` |
| `Models/` | Plan, Subscription, Invoice |
| `Services/PlanLimits.php` | Enforces the plan (extends the shared `App\Services\Limits`, which is "unlimited") |
| `Http/Controllers/` | `RegistrationController` (tenant sign-up), `PlanController` (`GET company/plan`) |
| `routes/api.php` | `POST auth/register`, `GET company/plan` (same URLs as before) |
| `Filament/` | Super-admin panel at `/super-admin`: companies, plans, subscriptions, invoices, stats widget |
| `Seeders/SuperAdminSeeder.php` | Creates the platform owner login |
| `database/migrations/` | `plans`, `subscriptions`, `invoices` tables and `plans.has_custom_roles` |

Frontend counterpart: `src/saas/` (the plan card on the Settings page).

## The rule

Shared code (everything outside `app/Saas` and `src/saas`) must not import anything from them.
It talks to SaaS behaviour only through `App\Services\Limits`, which does nothing in standalone and is swapped
for `PlanLimits` by `SaasServiceProvider` in SaaS mode. New SaaS features go in this folder, and anything
shared that needs a hook gets a small base class or interface in shared code, the way `Limits` works.

## Making a standalone build (later)

1. Delete `app/Saas/` and `src/saas/`.
2. Set `APP_MODE=standalone` in `.env.example`.
3. `bootstrap/providers.php` and `DatabaseSeeder` already skip the SaaS classes when the folder is missing.
4. Still to decide for the standalone package: `User` implements `FilamentUser`, so either keep the Filament
   package installed or move that interface out of `User`; `is_super_admin` and `companies.plan` columns are left
   in the shared schema.

## Running the seeder directly

`php artisan db:seed --class='App\Saas\Seeders\SuperAdminSeeder'`
