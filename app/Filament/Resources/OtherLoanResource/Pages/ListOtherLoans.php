<?php

namespace App\Filament\Resources\OtherLoanResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OtherLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherLoans extends ListRecords
{
    protected static string $resource = OtherLoanResource::class;

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
           ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', 'custom'))
           ->whereHas('inventory', fn (Builder $query) => $query->whereNotNull('item_name')->where('item_name', '!=', ''))
           ->where('quantity', '>', 0)
           ->where(fn (Builder $query) => $query->where('loan_status', 'approved')->orWhere('loan_status', 'partially_paid'))
        ),
       'Settled' => Tab::make('Settled')
       ->icon('heroicon-m-check-badge')
       ->modifyQueryUsing(fn (Builder $query) => $query
           ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', 'custom'))
           ->whereHas('inventory', fn (Builder $query) => $query->whereNotNull('item_name')->where('item_name', '!=', ''))
           ->where('quantity', '>', 0)
           ->where('loan_status', 'fully_paid')
        ),
       'Over Due' => Tab::make('Over Due')
       ->icon('heroicon-m-exclamation-triangle')
       ->modifyQueryUsing(fn (Builder $query) => $query
           ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', 'custom'))
           ->whereHas('inventory', fn (Builder $query) => $query->whereNotNull('item_name')->where('item_name', '!=', ''))
           ->where('quantity', '>', 0)
           ->where('loan_status', 'defaulted')
        ),
        ];
    }
}
