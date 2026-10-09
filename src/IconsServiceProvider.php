<?php

namespace Cloudcake\Components;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

class IconsServiceProvider extends \Illuminate\Support\ServiceProvider
{

    public function boot(): void
    {
        $this->bootAssets();
        $this->bootLanguage();
        $this->bootRoutes();
        $this->bootViews();

        $this->app->singleton(IconSetManager::class, function () {
            return new IconSetManager;
        });
    }

    private function bootAssets(): void
    {
        FilamentAsset::register([
            Css::make('cloudcake-filament-icons', __DIR__ . '/../resources/dist/css/main.css'),
        ], package: 'cloudcake-filament-icons');
    }

    private function bootLanguage(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'cloudcake-filament-icons');
    }

    private function bootRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }

    private function bootViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'cloudcake-filament-icons');
    }
}