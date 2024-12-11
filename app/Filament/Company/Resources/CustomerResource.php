<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\CustomerResource\Pages;
use App\Filament\Company\Resources\CustomerResource\RelationManagers;
use App\Models\Parties\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([Forms\Components\TextInput::make('customer_name')
                ->label('Customer Name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('customer_email')
                ->label('Email Address')
                ->email()
                ->required(),
            Forms\Components\TextInput::make('customer_phone')
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
            ->columns([
            Tables\Columns\TextColumn::make('customer_name')->label('Customer Name'),
            Tables\Columns\TextColumn::make('customer_email')->label('Email Address'),
            Tables\Columns\TextColumn::make('customer_phone')->label('Phone Number'),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
