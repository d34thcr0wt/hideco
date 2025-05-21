<?php

namespace App\Filament\Resources\MoneyLoanResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\MoneyLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMoneyLoans extends ListRecords
{
    protected static string $resource = MoneyLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs():array{
        return [
       'Active' => Tab::make('Active')
        ->icon('heroicon-m-arrow-path')
        ->modifyQueryUsing(fn (Builder $query) => $query
            ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', '!=', 'custom'))
            ->where('loan_status', 'approved')->orWhere('loan_status', 'partially_paid')),
       'Settled' => Tab::make('Settled')
       ->icon('heroicon-m-check-badge')
        ->modifyQueryUsing(fn (Builder $query) => $query
            ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', '!=', 'custom'))
            ->where('loan_status', 'fully_paid')),
       'Over Due' => Tab::make('Over Due')
       ->icon('heroicon-m-exclamation-triangle')
        ->modifyQueryUsing(fn (Builder $query) => $query
            ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', '!=', 'custom'))
            ->where('loan_status', 'defaulted')),
        ];
    }
}
