<?php

namespace App\Filament\Resources\OtherLoanRepaymentsResource\Pages;

use App\Filament\Resources\OtherLoanRepaymentsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherLoanRepayments extends ListRecords
{
    protected static string $resource = OtherLoanRepaymentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
} 