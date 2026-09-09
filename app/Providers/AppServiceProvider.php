<?php

namespace App\Providers;

use App\Models\Option;
use App\Sairox\License\LicenseService;
use App\Sairox\ThemeManager;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LicenseService::class);
        $this->app->singleton(ThemeManager::class);
    }

    public function boot(): void
    {
        Blade::if('feature', function (string $feature) {
            return app(LicenseService::class)->hasFeature($feature);
        });

        try {
            Option::where('autoload', 'yes')->get()->each(fn ($opt) => Cache::put("option_{$opt->option_name}", $opt->option_value, 86400));
        } catch (\Exception $e) {
            // Table may not exist yet (migrations not run)
        }

        FilamentView::registerRenderHook(
            PanelsRenderHook::PAGE_END,
            fn (): HtmlString => in_array(request()->route()?->getName(), [
                'filament.admin.resources.posts.edit',
                'filament.admin.resources.posts.create',
            ], true)
                ? new HtmlString(view('filament.posts.auto-save')->render())
                : new HtmlString(''),
        );
    }
}
