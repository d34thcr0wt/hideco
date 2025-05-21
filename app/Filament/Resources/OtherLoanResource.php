<?php

namespace App\Filament\Resources;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\Toggle;
use App\helpers\CreateLinks;
use Carbon\Carbon;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Filament\Resources\OtherLoanResource\Pages;
use App\Filament\Resources\OtherLoanResource\RelationManagers;
use Bavix\Wallet\Models\Wallet;
use Illuminate\Support\Facades\Storage;
use App\Models\Loan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Filament\Forms\Components\TextInput;

class OtherLoanResource extends Resource
{
    protected static ?string $model = \App\Models\Loan::class;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationLabel = 'Other';
    protected static ?string $navigationIcon = 'heroicon-s-circle-stack';
    protected static ?int $navigationSort = 2;

    protected static ?string $pluralModelLabel = 'Other Loans';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', 'custom'))->count();
    }

    public static function form(Form $form): Form
    {
        $options = Wallet::all()->map(function ($wallet) {
            return [
                'value' => $wallet->id,
                'label' => $wallet->name . ' - Balance: ' . number_format($wallet->balance),
            ];
        });

        return $form->schema([
            Forms\Components\Select::make('from_this_account')
                ->label('From this Account')
                ->prefixIcon('fas-wallet')
                ->options($options->pluck('label', 'value')->toArray())
                ->required()
                ->searchable(),
            Forms\Components\Select::make('loan_status')
                ->label('Loan Status')
                ->prefixIcon('fas-dollar-sign')
                ->options([
                    'processing' => 'Processing',
                    'approved' => 'Approved',
                    'denied' => 'Denied',
                    'defaulted' => 'Defaulted',
                ])
                ->required()
                ->hidden()
                ->default('approved'),
            Forms\Components\Select::make('borrower_id')
                ->prefixIcon('heroicon-o-user')
                ->relationship('borrower', 'full_name')
                ->preload()
                ->searchable()
                ->required(),
            Forms\Components\Select::make('inventory_id')
                ->label('Inventory Item')
                ->prefixIcon('heroicon-o-archive-box')
                ->relationship('inventory', 'item_name', fn (Builder $query) => $query->where('quantity', '>', 0))
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->getOptionLabelUsing(function ($value): string {
                    \Log::info('GetOptionLabelUsing: Processing value', ['value' => $value]);

                    $inventoryItem = \App\Models\Inventory::find($value);

                    \Log::info('GetOptionLabelUsing: Retrieved inventory item', ['item' => $inventoryItem]);

                    $quantity = $inventoryItem?->quantity ?? 0;
                    $formattedQuantity = number_format((int) $quantity, 0, '.', '');

                    $label = $inventoryItem?->item_name . ' (' . $formattedQuantity . ')';

                    \Log::info('GetOptionLabelUsing: Generated label', ['label' => $label]);

                    return $label;
                })
                ->afterStateUpdated(function (callable $set, callable $get) {
                    self::updatePrincipalAmount($set, $get);

                    $inventoryId = $get('inventory_id');
                    if ($inventoryId) {
                        $inventoryItem = \App\Models\Inventory::find($inventoryId);
                        if ($inventoryItem) {
                            $productType = $inventoryItem->product_type;

                            $loanType = \App\Models\LoanType::where('loan_name', $productType)
                                ->where('interest_cycle', 'custom')
                                ->first();

                            if ($loanType) {
                                $set('loan_type_id', $loanType->id);
                            } else {
                                $set('loan_type_id', null);
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('No matching Loan Type found')
                                    ->body("No custom loan type found with a name matching the selected inventory's product type ('{$productType}'). Please select a loan type manually if needed.")
                                    ->send();
                            }
                        } else {
                            $set('loan_type_id', null);
                        }
                    } else {
                        $set('loan_type_id', null);
                    }
                }),
            Forms\Components\TextInput::make('quantity')
                ->label('Quantity')
                ->prefixIcon('heroicon-o-calculator')
                ->required()
                ->numeric()
                ->default(1)
                ->minValue(1)
                ->step(1)
                ->live()
                ->afterStateUpdated(fn (callable $set, callable $get) => self::updatePrincipalAmount($set, $get)),
            Forms\Components\Select::make('loan_type_id')
                ->prefixIcon('heroicon-o-wallet')
                ->searchable()
                ->hidden()
                // ->relationship('loan_type', 'loan_name', fn ($query) => $query->where('interest_cycle', 'custom'))
                ->preload()
                ->required(),
            Forms\Components\DatePicker::make('loan_release_date')
                ->label('Loan Release Date')
                ->prefixIcon('heroicon-o-calendar')
                ->default(now())
                ->required()
                ->native(false)
                ->maxDate(now()),
            Forms\Components\TextInput::make('loan_duration')
                ->label('Loan Duration')
                ->prefixIcon('fas-clock')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('principal_amount')
                ->label('Amount')
                ->prefixIcon('fas-dollar-sign')
                ->required()
                ->numeric()
                ->readOnly()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;'])
                ->dehydrated(true),
            Forms\Components\TextInput::make('duration_period')
                ->label('Duration Period')
                ->prefixIcon('fas-clock')
                ->required()
                ->hidden()
                ->readOnly(),
            Forms\Components\TextInput::make('repayment_amount')
                ->label('Repayment Amount')
                ->prefixIcon('fas-coins')
                ->readOnly()
                ->numeric()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;']),
            Forms\Components\TextInput::make('interest_amount')
                ->label('Interest Amount')
                ->prefixIcon('fas-coins')
                ->numeric()
                ->readOnly()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;']),
            Forms\Components\TextInput::make('interest_rate')
                ->label('Interest Rate')
                ->prefixIcon('fas-percentage')
                ->hidden()
                ->numeric(),
            Forms\Components\TextInput::make('amortization_amount')
                ->label('Amortization Amount')
                ->prefixIcon('fas-dollar-sign')
                ->numeric()
                ->readOnly()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;']),
            Forms\Components\DatePicker::make('loan_due_date')
                ->label('Loan Due Date')
                ->prefixIcon('heroicon-o-calendar')
                ->hidden()
                ->native(false),
            Forms\Components\Toggle::make('activate_loan_agreement_form')
                ->label('Compile Loan Agreement Form')
                ->hidden()
                ->helperText('If you want to compile the loan agreement for this applicant make sure you have added the loan loan agreement form template for this type of loan.')
                ->onColor('success')
                ->offColor('danger'),
            Forms\Components\TextInput::make('loan_agreement_file_path')->hidden(),
            Forms\Components\TextInput::make('balance')->hidden(),
        ]);
    }

    // Helper function to update the principal amount
    private static function updatePrincipalAmount(callable $set, callable $get): void
    {
        $inventoryId = $get('inventory_id');
        $quantity = $get('quantity');

        if ($quantity > 0) {
            $inventoryItem = \App\Models\Inventory::find($inventoryId);
            if ($inventoryItem) {
                $calculatedPrice = $inventoryItem->selling_price * $quantity;
                $set('principal_amount', $calculatedPrice);
            } else {
                $set('principal_amount', 0);
            }
        } else {
             $set('principal_amount', -1);
        }
    }

    public static function table(Table $table): Table
    {
        $create_link = new CreateLinks();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('borrower.full_name')->searchable(),
                Tables\Columns\TextColumn::make('loan_type.loan_name')
                    ->label('Type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('inventory.item_name')
                    ->label('Item')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => number_format((int) $state, 0, '.', '')),
                Tables\Columns\TextColumn::make('calculated_profit')
                    ->label('Calculated Profit')
                    ->getStateUsing(fn (\App\Models\Loan $record): ?float => ($record->inventory->selling_price * $record->quantity) - ($record->inventory->original_price * $record->quantity))
                    ->money('PHP')
                    ->badge(),
                Tables\Columns\TextColumn::make('principal_amount')
                    ->label('Principle Amount')
                    ->money('PHP')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Balance')
                    ->money('PHP')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_due_date')
                    ->label('Due Date')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('loan_number')
                    ->label('Loan Number')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('loan_status')
                    ->options([
                        'approved' => 'Approved',
                        'defaulted' => 'Defaulted',
                        'partially_paid' => 'Partially Paid',
                        'fully_paid' => 'Fully Paid',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                ])
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', 'custom'))
                ->whereHas('inventory', fn (Builder $query) => $query->whereNotNull('item_name')->where('item_name', '!=', ''))
                ->where('quantity', '>', 0)
            );
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOtherLoans::route('/'),
            'create' => Pages\CreateOtherLoan::route('/create'),
            'view' => Pages\ViewOtherLoan::route('/{record}'),
            'edit' => Pages\EditOtherLoan::route('/{record}/edit'),
        ];
    }
} 