<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\SupplierResource\Pages;
use App\Filament\Company\Resources\SupplierResource\RelationManagers;
use App\Models\Parties\Supplier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
            
                Forms\Components\TextInput::make('supplier_name')
                    ->label('Supplier Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('supplier_email')
                    ->label('Email Address')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('supplier_phone')
                    ->label('Phone Number')
                    ->required(),
                Forms\Components\TextInput::make('address')
                    ->label('Address')
                    ->nullable(),
                Forms\Components\TextInput::make('city')
                ->label('City')
                ->nullable(),
                Forms\Components\TextInput::make('country')
                ->label('Country')
                ->nullable(),
            
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([Tables\Columns\TextColumn::make('supplier_name')->label('Supplier Name'),
            Tables\Columns\TextColumn::make('supplier_email')->label('Email Address'),
            Tables\Columns\TextColumn::make('supplier_phone')->label('Phone Number'),
            Tables\Columns\TextColumn::make('address')->label('Address'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
