<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationGroup = 'Management';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_type')
                    ->options(Inventory::getProductTypeOptions())
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => 
                        $set('grams', null)
                        ->set('amount_per_gram', null)
                        ->set('downpayment', null)
                    ),

                Forms\Components\TextInput::make('item_name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('borrower_name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\DatePicker::make('borrow_date')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->visible(fn (Forms\Get $get) => 
                        in_array($get('product_type'), [Inventory::TYPE_JAPAN, Inventory::TYPE_LABUBU])
                    ),

                Forms\Components\TextInput::make('grams')
                    ->numeric()
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('product_type') === Inventory::TYPE_GOLD),

                Forms\Components\TextInput::make('amount_per_gram')
                    ->numeric()
                    ->required()
                    ->visible(fn (Forms\Get $get) => $get('product_type') === Inventory::TYPE_GOLD),

                Forms\Components\TextInput::make('original_price')
                    ->required()
                    ->numeric()
                    ->prefix('₱'),

                Forms\Components\TextInput::make('selling_price')
                    ->required()
                    ->numeric()
                    ->prefix('₱'),

                Forms\Components\TextInput::make('downpayment')
                    ->numeric()
                    ->prefix('₱')
                    ->visible(fn (Forms\Get $get) => $get('product_type') === Inventory::TYPE_GOLD),

                Forms\Components\Select::make('status')
                    ->options(Inventory::getStatusOptions())
                    ->required()
                    ->default(Inventory::STATUS_ACTIVE),

                Forms\Components\Textarea::make('notes')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Inventory::TYPE_GOLD => 'warning',
                        Inventory::TYPE_JAPAN => 'success',
                        Inventory::TYPE_LABUBU => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Inventory::TYPE_GOLD => 'Gold',
                        Inventory::TYPE_JAPAN => 'Japan',
                        Inventory::TYPE_LABUBU => 'Labubu',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('item_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('borrower_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('borrow_date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable()
                    ->visible(fn ($record) => in_array($record->product_type, [Inventory::TYPE_JAPAN, Inventory::TYPE_LABUBU])),

                Tables\Columns\TextColumn::make('grams')
                    ->numeric()
                    ->sortable()
                    ->visible(fn ($record) => $record->product_type === Inventory::TYPE_GOLD),

                Tables\Columns\TextColumn::make('original_price')
                    ->money('PHP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->money('PHP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('downpayment')
                    ->money('PHP')
                    ->sortable()
                    ->visible(fn ($record) => $record->product_type === Inventory::TYPE_GOLD),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => Inventory::STATUS_ACTIVE,
                        'danger' => Inventory::STATUS_SOLD,
                        'warning' => Inventory::STATUS_RETURNED,
                    ]),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_type')
                    ->options(Inventory::getProductTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->options(Inventory::getStatusOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
} 