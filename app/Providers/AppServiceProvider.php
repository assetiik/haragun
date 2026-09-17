<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', function ($user = null): bool {
            return true;
        });

        JsonResource::withoutWrapping();

        Table::configureUsing(function (Table $table): void {
            $table
                ->paginationPageOptions([10, 25, 50])
                ->defaultPaginationPageOption(10)
                ->extremePaginationLinks();
        });
    }
}
