<?php

namespace App\Filament\Resources\OtherLoanResource\Pages;

use App\Filament\Resources\OtherLoanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOtherLoan extends ViewRecord
{
    protected static string $resource = OtherLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
