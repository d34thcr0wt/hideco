<?php

namespace App\Providers;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
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
        Model::unguard();
        Filament::registerNavigationGroups([
            'Customers',
            'Inventories',
            'Loans',            
            'Repayments',
            'Expenses',
            'Wallets',
            'Loan Agreement Forms',
            'Addons',
        ]);
    }
}