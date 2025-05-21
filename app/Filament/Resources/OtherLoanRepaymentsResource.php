<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OtherLoanRepaymentsResource\Pages;
use App\Filament\Resources\OtherLoanRepaymentsResource\RelationManagers;
use App\Models\Repayments;
use App\Models\Loan;
use App\Models\Inventory;
use App\Models\Borrower;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Filament\Notifications\Notification;


class OtherLoanRepaymentsResource extends Resource
{
    // TODO: Update this to your actual OtherLoanRepayment model
    protected static ?string $model = Repayments::class;

    // TODO: Choose an appropriate icon
    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationLabel = 'Items';
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square';
    protected static ?int $navigationSort = 3;
    protected static ?string $pluralModelLabel = 'Other Repayments';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::whereHas('loan_number', fn (Builder $query) => $query->where('inventory_id', '>', 0))->count();

        return $count > 0 ? (string) $count : null;
    }
    
    // TODO: Set a navigation label if needed
    // protected static ?string $navigationLabel = 'Other Loan Repayments';

    // TODO: Set a navigation group if needed, e.g., 'Loan Management'
    // protected static ?string $navigationGroup = 'Loan Management';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('borrower_id')
                    ->prefixIcon('heroicon-o-currency-dollar')
                    ->options(function () {
                         // Filter borrowers who have loans linked to inventory with quantity > 0
                         return Borrower::whereHas('loans', function (Builder $loanQuery) {
                             $loanQuery->where('inventory_id', '>', 0)
                                       ->whereHas('inventory', function (Builder $inventoryQuery) {
                                           $inventoryQuery->where('quantity', '>', 0);
                                       });
                         })->pluck('full_name', 'id'); // Assuming 'full_name' is available on Borrower model
                    })
                    ->preload()
                    ->searchable()
                    ->required()
                    ->label('Borrower')
                    ->live(),
                Forms\Components\Select::make('inventory_id')
                ->label('Inventory Item')
                ->options(function (Forms\Get $get) {
                    $borrowerId = $get('borrower_id');
                    if ($borrowerId) {
                         // Get inventory items linked to loans for this borrower
                         $inventoryIds = Loan::where('borrower_id', $borrowerId)
                                            ->whereNotNull('inventory_id')
                                            ->where('balance', '>', 0) // Filter for loans with pending balance
                                            ->pluck('inventory_id')
                                            ->unique();

                         // Filter inventory items by quantity > 0
                         return Inventory::whereIn('id', $inventoryIds)
                                          ->where('quantity', '>', 0) // Ensure inventory quantity > 0
                                          ->pluck('item_name', 'id');
                    }
                    return []; // Return empty if no borrower is selected
                })
                ->searchable()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get) {
                    $borrowerId = $get('borrower_id');
                    $inventoryId = $state; // The selected inventory ID

                    if ($borrowerId && $inventoryId) {
                        // Find the loan for the selected borrower and inventory item with a positive balance
                        $loan = Loan::where('borrower_id', $borrowerId)
                                    ->where('inventory_id', $inventoryId)
                                    ->where('balance', '>', 0) // Only consider loans with remaining balance
                                    ->oldest() // Get the oldest one if multiple exist (adjust if needed)
                                    ->first();

                        if ($loan) {
                            // Set the balance and loan number fields
                            $set('balance', $loan->balance);
                            $set('loan_number', $loan->loan_number); // Also set loan number for clarity
                        } else {
                            // Clear fields if no matching loan is found
                            $set('balance', null);
                            $set('loan_number', null);
                             Notification::make()
                                ->warning()
                                ->title('No Active Loan Found')
                                ->body('No active loan found for the selected borrower and inventory item with a pending balance.')
                                ->send();
                        }
                    } else {
                        // Clear fields if borrower or inventory is not selected
                        $set('balance', null);
                        $set('loan_number', null);
                    }
                }),
                Forms\Components\DatePicker::make('payment_date')->label('Payment Date')->prefixIcon('heroicon-o-calendar')->live()->native(false)->maxDate(now())->default(now()),
                Forms\Components\Select::make('payments_method')
                    ->label('Payment Method')
                    ->prefixIcon('fas-dollar-sign')
                    ->required()
                    ->options([
                        'bank_transfer' => 'Bank Transfer',
                        'mobile_money' => 'Mobile Money',
                        'cheque' => 'Cheque',
                        'cash' => 'Cash',
                    ])
                    ->default('cash'),
                Forms\Components\TextInput::make('payments')
                    ->label('Payment Amount')
                    ->prefixIcon('fas-dollar-sign')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->rules([
                        function (Forms\Get $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                $currentBalance = (float) str_replace(',', '', $get('balance')); // Get the current balance, remove commas

                                if ($currentBalance !== null && (float) $value > $currentBalance) {
                                    $fail("The payment amount cannot be greater than the current balance ({$get('balance')}).");
                                }
                            };
                        },
                    ]),
                Forms\Components\TextInput::make('reference_number')->label('Transaction Reference')->prefixIcon('fas-dollar-sign'),
                Forms\Components\TextInput::make('balance')->label('Current Balance')->prefixIcon('fas-dollar-sign')->disabled()->readOnly()->extraAttributes(['style' => 'background-color: #f0f0f0;']),
                Forms\Components\TextInput::make('loan_number')->label('Loan number')->prefixIcon('fas-coins')->disabled()->readOnly()->extraAttributes(['style' => 'background-color: #f0f0f0;']),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('loan_number.borrower.full_name')->label('Borrower Name')->searchable()->sortable()
                , Tables\Columns\TextColumn::make('loan_number.loan_number')->searchable()->sortable()
                , Tables\Columns\TextColumn::make('reference_number')->label('Reference Number')->searchable()
                , Tables\Columns\TextColumn::make('payment_date')->label('Payments Date')->searchable()->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M d, Y') : null)->sortable()
                , Tables\Columns\TextColumn::make('loan_number.loan_status')->label('Loan Status')->badge()->searchable()
                , Tables\Columns\TextColumn::make('payments')->searchable()
                , Tables\Columns\TextColumn::make('balance')->searchable()
            ])
            ->filters([
                Tables\Filters\Filter::make('inventory_loans')
                    ->query(fn (Builder $query): Builder => $query->whereHas('loan_number', fn (Builder $query) => $query->where('inventory_id', '>=', 1)))
                    ->label('Inventory Loans')
                    ->default(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // TODO: Define relation managers if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtherLoanRepayments::route('/'),
            'create' => Pages\CreateOtherLoanRepayments::route('/create'),
            'view' => Pages\ViewOtherLoanRepayments::route('/{record}'),
            'edit' => Pages\EditOtherLoanRepayments::route('/{record}/edit'),
        ];
    }

    // TODO: Add this method if you use soft deletes
    // public static function getEloquentQuery(): Builder
    // {
    //     return parent::getEloquentQuery()
    //         ->withoutGlobalScopes([
    //             SoftDeletingScope::class,
    //         ]);
    // }
} 