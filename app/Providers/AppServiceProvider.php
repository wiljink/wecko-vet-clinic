<?php

namespace App\Providers;

use App\Support\FilipinoFaker;
use Faker\Generator as FakerGenerator;
use Filament\Infolists\Infolist;
use Filament\Support\Facades\FilamentView;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Prepend Filipino name data to every Faker generator (seeders + factories).
        $this->app->afterResolving(FakerGenerator::class, function (FakerGenerator $faker): void {
            static $patched = [];

            $id = spl_object_id($faker);

            if (isset($patched[$id])) {
                return;
            }

            $patched[$id] = true;
            $faker->addProvider(new FilipinoFaker($faker));
        });
    }

    public function boot(): void
    {
        // The principal (practice owner) bypasses every permission check.
        Gate::before(fn ($user, string $ability) => $user->hasRole('principal') ? true : null);

        // Money is displayed in Philippine pesos (₱) across the admin panel.
        Table::$defaultCurrency = 'PHP';
        Table::$defaultNumberLocale = 'en_PH';
        Infolist::$defaultCurrency = 'PHP';
        Infolist::$defaultNumberLocale = 'en_PH';
    }
}
