<?php

namespace App\Saas\Filament;

use App\Saas\Filament\Pages\Profile;
use App\Saas\Filament\Widgets\LatestCompanies;
use App\Saas\Filament\Widgets\PlatformStatsOverview;
use App\Saas\Filament\Widgets\TenantGrowthChart;
use App\Saas\Models\PlatformSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class SuperAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $logo = PlatformSetting::get('logo');
        $favicon = PlatformSetting::get('favicon');

        return $panel
            ->id('super-admin')
            ->path('super-admin')
            ->login()
            ->profile(Profile::class, isSimple: false)
            // Brand: name, logo, favicon and accent colour come from Platform > Settings.
            ->brandName(PlatformSetting::get('app_name', 'SPMS Super Admin'))
            ->brandLogo($logo ? asset('storage/'.$logo) : null)
            ->brandLogoHeight('2.25rem')
            ->favicon($favicon ? asset('storage/'.$favicon) : null)
            ->colors(['primary' => $this->accent(PlatformSetting::get('primary_color', 'indigo'))])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(MaxWidth::Full)
            ->navigationGroups(['Tenants', 'Billing', 'Platform'])
            ->discoverResources(in: app_path('Saas/Filament/Resources'), for: 'App\\Saas\\Filament\\Resources')
            ->discoverPages(in: app_path('Saas/Filament/Pages'), for: 'App\\Saas\\Filament\\Pages')
            ->pages([Pages\Dashboard::class])
            ->widgets([PlatformStatsOverview::class, TenantGrowthChart::class, LatestCompanies::class])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString($this->styles()))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }

    private function accent(string $name): array
    {
        return match ($name) {
            'blue' => Color::Blue,
            'sky' => Color::Sky,
            'teal' => Color::Teal,
            'emerald' => Color::Emerald,
            'amber' => Color::Amber,
            'orange' => Color::Orange,
            'rose' => Color::Rose,
            'violet' => Color::Violet,
            default => Color::Indigo,
        };
    }

    /** A few small touches: softer page background, rounder cards, calmer sidebar. */
    private function styles(): string
    {
        return <<<'HTML'
<style>
    html:not(.dark) .fi-body { background-color: #f5f6fa; }
    .fi-section, .fi-ta-ctn, .fi-wi-stats-overview-stat, .fi-wi-chart, .fi-simple-main { border-radius: 0.875rem; }
    .fi-wi-stats-overview-stat { padding: 1.25rem; }
    .fi-sidebar { border-right: 1px solid rgb(0 0 0 / 0.06); }
    .dark .fi-sidebar { border-right-color: rgb(255 255 255 / 0.08); }
    .fi-sidebar-group-label { letter-spacing: 0.04em; text-transform: uppercase; font-size: 0.7rem; }
    .fi-sidebar-item-active > a { font-weight: 600; }
    .fi-header-heading { font-weight: 700; letter-spacing: -0.01em; }
    .fi-topbar > nav { box-shadow: 0 1px 0 rgb(0 0 0 / 0.05); }
</style>
HTML;
    }
}
