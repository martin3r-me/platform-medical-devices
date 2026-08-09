<?php

namespace Platform\MedicalDevices;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\MedicalDevices\Services\CoreObservationClient;
use Platform\MedicalDevices\Services\GdtParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Medizinische Messgeräte — Praxis-Modul.
 *
 * Verantwortung: Geräte-Registry (+ Token), GDT-Eingang (Token-API, Ziel des lokalen
 * Windows-Agents), Parsing/Matching/Strip, Arzt-Bestätigung, und Weiterleitung an den
 * blinden sovra-Core über die Bridge (CoreObservationClient).
 */
class MedicalDevicesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/medical-devices.php', 'medical-devices');

        $this->app->singleton(GdtParser::class, fn () => new GdtParser());
        $this->app->singleton(CoreObservationClient::class, fn ($app) => new CoreObservationClient(
            (string) config('medical-devices.core.base'),
            (string) config('medical-devices.core.token'),
            (int) config('medical-devices.core.timeout', 8),
        ));
    }

    public function boot(): void
    {
        if (
            config()->has('medical-devices.routing') &&
            config()->has('medical-devices.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'medical-devices',
                'title'      => 'Medizinische Messgeräte',
                'routing'    => config('medical-devices.routing'),
                'guard'      => config('medical-devices.guard'),
                'navigation' => config('medical-devices.navigation'),
                'sidebar'    => config('medical-devices.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('medical-devices')) {
            ModuleRouter::group('medical-devices', function () {
                $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
            });
        }

        // GDT-Eingang: Token-API (Ziel des lokalen Windows-Agents). Kein Web-Auth,
        // stattdessen Geräte-Token (Bearer) → Gerät + Team werden aufgelöst.
        $this->app['router']->aliasMiddleware('medical-devices.device.token', \Platform\MedicalDevices\Http\Middleware\DeviceTokenAuth::class);
        Route::prefix('api/medical-devices')
            ->middleware(['api', 'medical-devices.device.token'])
            ->group(__DIR__ . '/../routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'medical-devices');

        $this->registerLivewireComponents();
    }

    /** Datei src/Livewire/Inbox/Index.php -> alias medical-devices.inbox.index */
    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\MedicalDevices\\Livewire';
        $prefix = 'medical-devices';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $class = $baseNamespace . '\\' . str_replace(['/', '.php'], ['\\', ''], $relativePath);
            if (!class_exists($class)) {
                continue;
            }
            $alias = $prefix . '.' . str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            Livewire::component($alias, $class);
        }
    }
}
