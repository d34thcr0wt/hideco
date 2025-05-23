<?php

namespace App\Filament\Resources;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\Toggle;
use App\helpers\CreateLinks;
use Carbon\Carbon;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Filament\Resources\MoneyLoanResource\Pages;
use App\Filament\Resources\MoneyLoanResource\RelationManagers;
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

class MoneyLoanResource extends Resource
{
    protected static ?string $model = Loan::class;

    protected static ?string $navigationGroup = 'Loans';
    protected static ?string $navigationLabel = 'Money';
    protected static ?string $navigationIcon = 'heroicon-s-banknotes';
    protected static ?int $navigationSort = 2;
    protected static ?string $pluralModelLabel = 'Money';

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', '!=', 'custom'))->count();
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
            Forms\Components\TextInput::make('principal_amount')
                ->label('Principle Amount')
                ->prefixIcon('fas-dollar-sign')
                ->required()
                ->numeric(),
            Forms\Components\Select::make('loan_type_id')
                ->prefixIcon('heroicon-o-wallet')
                ->relationship('loan_type', 'loan_name', fn ($query) => $query->where('interest_cycle', '!=', 'custom'))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('loan_duration')
                ->label('Loan Duration')
                ->prefixIcon('fas-clock')
                ->required()
                ->numeric(),
            Forms\Components\TextInput::make('duration_period')
                ->label('Duration Period')
                ->prefixIcon('fas-clock')
                ->required()
                ->hidden()
                ->readOnly(),
            Forms\Components\DatePicker::make('loan_release_date')
                ->label('Loan Release Date')
                ->prefixIcon('heroicon-o-calendar')
                ->default(now())
                ->required()
                ->native(false)
                ->maxDate(now()),
            Forms\Components\TextInput::make('repayment_amount')
                ->label('Repayment Amount')
                ->prefixIcon('fas-coins')
                ->readOnly()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;'])
                ->numeric(),
            Forms\Components\TextInput::make('interest_amount')
                ->label('Interest Amount')
                ->prefixIcon('fas-coins')
                ->numeric()
                ->extraAttributes(['style' => 'background-color: #f0f0f0;'])
                ->readOnly(),
            Forms\Components\TextInput::make('interest_rate')
                ->label('Interest Rate')
                ->prefixIcon('fas-percentage')
                ->hidden()
                ->numeric(),
            Forms\Components\TextInput::make('amortization_amount')
                ->label('Amortization Amount')
                ->prefixIcon('fas-dollar-sign')
                ->extraAttributes(['style' => 'background-color: #f0f0f0;'])
                ->numeric()
                ->readOnly(),
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

    public static function table(Table $table): Table
    {
        $create_link = new CreateLinks();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('borrower.full_name')->searchable(),
                Tables\Columns\TextColumn::make('loan_type.loan_name')->searchable(),
                Tables\Columns\TextColumn::make('loan_status')
                    ->badge()
                    ->color(
                        fn(string $state): string => match ($state) {
                            'requested' => 'gray',
                            'processing' => 'info',
                            'approved' => 'success',
                            'fully_paid' => 'success',
                            'denied' => 'danger',
                            'defaulted' => 'warning',
                            default => 'warning',
                        },
                    )
                    ->searchable(),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_number')
                    ->label('Loan Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('loan_agreement_file_path')
                    ->label('Loan Agreement Form')
                    ->formatStateUsing(fn (string $state) => 
                        "<a href='".url($state)."' 
                            download 
                            class='inline-flex items-center px-3 py-1 bg-primary-500 text-white text-sm font-medium rounded-lg shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-5 h-5 mr-1'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M12 3v12m0 0l-3-3m3 3l3-3m-9 9h12' />
                            </svg>
                            Download
                        </a>"
                    )
                    ->html(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('loan_status')
                    ->options([
                        'requested' => 'Requested',
                        'processing' => 'Processing',
                        'approved' => 'Approved',
                        'denied' => 'Denied',
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
                ->whereHas('loan_type', fn (Builder $query) => $query->where('interest_cycle', '!=', 'custom'))
                ->where('quantity', 0)
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
            'index' => Pages\ListMoneyLoans::route('/'),
            'create' => Pages\CreateMoneyLoan::route('/create'),
            'view' => Pages\ViewMoneyLoan::route('/{record}'),
            'edit' => Pages\EditMoneyLoan::route('/{record}/edit'),
        ];
    }
} 