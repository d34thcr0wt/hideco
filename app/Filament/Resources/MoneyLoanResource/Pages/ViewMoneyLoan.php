<?php

namespace App\Filament\Resources\MoneyLoanResource\Pages;

use App\Filament\Resources\MoneyLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMoneyLoan extends ViewRecord
{
    protected static string $resource = MoneyLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
