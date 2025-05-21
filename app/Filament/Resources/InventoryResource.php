<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Filament\Resources\InventoryResource\RelationManagers;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-s-archive-box';
    protected static ?string $navigationLabel = 'Inventory';
    protected static ?string $navigationGroup = 'Inventories';

    protected static ?int $navigationSort = 3; // Assuming customers is 2

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_type')
                    ->label('Product Type')
                    ->options([
                        'gold' => 'Gold',
                        'japan' => 'Japan Products',
                        ])
                    ->required(),
                Forms\Components\TextInput::make('item_name')
                    ->label('Item Name')
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),
                Forms\Components\TextInput::make('original_price')
                    ->label('Original Price')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('selling_price')
                    ->label('Selling Price')
                    ->numeric()
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->hidden()
                    ->default('active'),
            ]);
    }


    public static function table(Table $table):
        Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('product_type')
                    ->label('Product Type')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('item_name')
                    ->label('Item Name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => number_format((int) $state, 0, '.', '')),
                Tables\Columns\TextColumn::make('original_price')
                    ->label('Original Price')
                    ->money('PHP')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('selling_price')
                    ->label('Selling Price')
                    ->money('PHP')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('calculated_profit')
                ->label('Calculated Profit')
                ->getStateUsing(fn (\App\Models\Inventory $record): ?float => ($record->selling_price * $record->item_sold) - ($record->original_price * $record->item_sold))
                ->money('PHP')
                ->badge()
                ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->hidden()
                    ->sortable(),
            ])
            ->filters([
                // Add filters here later
            ])
            ->actions([
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
            // Define relationships here later
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

    public static function canCreate(): bool
    {
        return true;
    }

    public static function canEdit($record): bool
    {
        return true;
    }

    public static function canDelete($record): bool
    {
        return true;
    }

    public static function canViewAny(): bool
    {
        return true;
    }
} 
