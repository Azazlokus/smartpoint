<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\AdapterFactory;
use App\Adapters\BlogSourceAdapter;
use App\Adapters\Contracts\AdapterFactoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Регистрация сервисов приложения.
     */
    public function register(): void
    {
        // AdapterFactory получает карту адаптеров из config/monitoring.php.
        // Добавить новый источник = одна строка в конфиге, без правки фабрики.
        $this->app->singleton(AdapterFactoryInterface::class, function (): AdapterFactory {
            /** @var array<string, class-string<BlogSourceAdapter>> $adapters */
            $adapters = config('monitoring.adapters', []);

            return new AdapterFactory($adapters);
        });
    }

    /**
     * Инициализация сервисов приложения.
     */
    public function boot(): void
    {
        //
    }
}
