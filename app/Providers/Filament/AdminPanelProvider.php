<?php

namespace App\Providers\Filament;

use App\Filament\Navigation\AdminNavigation;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetAdminLocale;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->brandName('Haragun Admin')
            ->brandLogo(null)
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::hex('#4B4DED'),
                'gray' => Color::Slate,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Sky,
            ])
            ->font('Manrope')
            ->darkMode(false)
            ->defaultThemeMode(ThemeMode::Light)
            ->globalSearch(false)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Haragun'),
                NavigationGroup::make('Кофейни'),
                NavigationGroup::make('Сайт'),
                NavigationGroup::make('Заказы'),
                NavigationGroup::make('Клиенты'),
            ])
            ->navigationItems(AdminNavigation::items())
            ->resourceCreatePageRedirect('index')
            ->resourceEditPageRedirect('index')
            ->authGuard('admin')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                function (): string {
                    if (! request()->routeIs('filament.admin.auth.login')) {
                        return '';
                    }

                    // Не зависим от html.dark — Filament с ThemeMode::Light снимает его через Alpine
                    return <<<'HTML'
<style id="haragun-login-theme">
html, body.fi-body, .fi-simple-layout, .fi-simple-main-ctn {
    background: #030712 !important;
    background-color: #030712 !important;
    color: #ffffff !important;
}
.fi-simple-main {
    max-width: 28rem !important;
    background: #111827 !important;
    background-color: #111827 !important;
    color: #ffffff !important;
    border-radius: 0.75rem !important;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.1) !important;
    --tw-shadow: 0 0 #0000 !important;
    --tw-ring-shadow: 0 0 #0000 !important;
}
.fi-simple-page,
.fi-simple-page-content,
.fi-simple-header {
    background: transparent !important;
    background-color: transparent !important;
    color: #ffffff !important;
    box-shadow: none !important;
}
.fi-simple-main .fi-logo,
.fi-simple-main .fi-logo-text,
.fi-simple-header-heading,
.fi-simple-main label,
.fi-simple-main .fi-fo-field-wrp-label,
.fi-simple-main .fi-fo-field-wrp-label span,
.fi-simple-main .fi-fo-field-label,
.fi-simple-main .fi-checkbox-label,
.fi-simple-main .fi-fo-field-label-content {
    color: #ffffff !important;
}
.fi-simple-main .fi-input-wrp {
    background: #030712 !important;
    background-color: #030712 !important;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.12) !important;
    --tw-ring-shadow: 0 0 #0000 !important;
    --tw-shadow: 0 0 #0000 !important;
}
.fi-simple-main input.fi-input,
.fi-simple-main .fi-input {
    background: transparent !important;
    background-color: transparent !important;
    color: #ffffff !important;
    box-shadow: none !important;
}
.fi-simple-main .fi-icon-btn {
    background: transparent !important;
    color: #9ca3af !important;
    box-shadow: none !important;
}
.fi-simple-main .fi-checkbox-input {
    background: #030712 !important;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.2) !important;
}
.fi-simple-main .fi-btn.fi-color-primary,
.fi-simple-main button.fi-btn.fi-color-primary {
    background: #4b4ded !important;
    background-color: #4b4ded !important;
    color: #ffffff !important;
    box-shadow: none !important;
}
</style>
HTML;
                },
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                function (): string {
                    // Светлая админ-тема не должна ломать логин
                    if (request()->routeIs('filament.admin.auth.login')) {
                        return '';
                    }

                    return '<link rel="stylesheet" href="'.asset('css/haragun-admin.css').'?v=26">';
                },
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn (): string => '<div class="haragun-nav-label">Управление</div>',
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetAdminLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
