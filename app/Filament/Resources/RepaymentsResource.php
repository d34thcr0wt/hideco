<?php

namespace App\Filament\Resources;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Set;
use Filament\Forms\Get;
use App\Filament\Resources\RepaymentsResource\Pages;
use App\Filament\Resources\RepaymentsResource\RelationManagers;
use App\Models\Repayments;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Carbon\Carbon;
use Filament\Notifications\Notification;


class RepaymentsResource extends Resource
{
    protected static ?string $model = Repayments::class;

    protected static ?string $navigationGroup = 'Repayments';
    protected static ?string $navigationIcon = 'fas-dollar-sign';
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('borrower_id')
                ->label('Borrower')
                ->prefixIcon('heroicon-o-wallet')
                ->relationship('borrower_name', 'full_name', function ($query) {
                    $query->whereHas('loans'); // Ensures only borrowers with loans are shown
                })
                ->searchable()
                ->preload()
                ->live(onBlur: true)
                // ->required(),
                ->required(function ($state, Set $set) {
                    if ($state) {          
                        $loan = \App\Models\Loan::where('borrower_id', (int) $state)
                                    ->where('balance', '>', 0)
                                    ->whereIn('loan_status', ['processing', 'approved', 'partially_paid'])
                                    ->orderBy('loan_number', 'asc')
                                    ->first();
                        $set('payment_date', now()->format('Y-m-d'));
                        $set('loan_number', $loan->loan_number);
                        $set('balance', $loan->balance);
                    }
                    return true;
                }),
            Forms\Components\DatePicker::make('payment_date')->label('Payment Date')->prefixIcon('heroicon-o-calendar')->live()->native(false)->maxDate(now()),
            Forms\Components\TextInput::make('payments')->label('Payment Amount')->prefixIcon('fas-dollar-sign')->required(),
            Forms\Components\Select::make('payments_method')
                ->label('Payment Method')
                ->prefixIcon('fas-dollar-sign')
                ->required()
                ->options([
                    'bank_transfer' => 'Bank Transfer',
                    'mobile_money' => 'Mobile Money',
                    'cheque' => 'Cheque',
                    'cash' => 'Cash',
                ]),
            Forms\Components\TextInput::make('reference_number')->label('Transaction Reference')->prefixIcon('fas-dollar-sign'),
            Forms\Components\TextInput::make('balance')->label('Current Balance')->prefixIcon('fas-dollar-sign')->disabled()->readOnly()->extraAttributes(['class' => 'bg-red-200 text-red-700 cursor-not-allowed']),
            Forms\Components\TextInput::make('loan_number')->label('Loan number')->prefixIcon('fas-coins')->disabled()->readOnly()->extraAttributes(['class' => 'bg-red-200 text-red-700 cursor-not-allowed']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('borrower_name.full_name')->label('Borrower Name')->searchable()->sortable()
                    , Tables\Columns\TextColumn::make('loan_number.loan_number')->searchable()->sortable()
                    , Tables\Columns\TextColumn::make('reference_number')->label('Reference Number')->searchable()
                    , Tables\Columns\TextColumn::make('payment_date')->label('Payments Date')->searchable()->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('M d, Y') : null)->sortable()
                    , Tables\Columns\TextColumn::make('loan_number.loan_status')->label('Loan Status')->badge()->searchable()
                    , Tables\Columns\TextColumn::make('payments')->searchable()
                    , Tables\Columns\TextColumn::make('balance')->searchable()])
            ->filters([
                Tables\Filters\SelectFilter::make('payments_method')->options([
                    'bank_transfer' => 'Bank Transfer',
                    'mobile_money' => 'Mobile Money',
                    'pemic' => 'PEMIC',
                    'cheque' => 'Cheque',
                    'cash' => 'Cash',
                ]),
            ])
            ->defaultSort('borrower_name.full_name', 'asc')     // ✅ Sort Borrower Name (ASC)
            ->defaultSort('loan_number.loan_number', 'desc')    // ✅ Sort Loan Number (DESC)
            ->defaultSort('balance', 'asc')               // ✅ Sort Payment Date (DESC)            
            ->actions([
                // Tables\Actions\ViewAction::make(),
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make(), ExportBulkAction::make()])])
            ->emptyStateActions([Tables\Actions\CreateAction::make()]);
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
            'index' => Pages\ListRepayments::route('/'),
            'create' => Pages\CreateRepayments::route('/create'),
            'view' => Pages\ViewRepayments::route('/{record}'),
            'edit' => Pages\EditRepayments::route('/{record}/edit'),
        ];
    }
}
