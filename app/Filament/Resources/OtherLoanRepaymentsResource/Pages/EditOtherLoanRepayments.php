<?php

namespace App\Filament\Resources\OtherLoanRepaymentsResource\Pages;

use App\Filament\Resources\OtherLoanRepaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOtherLoanRepayments extends EditRecord
{
    protected static string $resource = OtherLoanRepaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
} 