<?php

namespace App\Filament\Resources\OtherLoanRepaymentsResource\Pages;

use App\Filament\Resources\OtherLoanRepaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewOtherLoanRepayments extends ViewRecord
{
    protected static string $resource = OtherLoanRepaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
} 