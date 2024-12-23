<?php

namespace App\Filament\Company\Resources;

use App\Enums\Accounting\AdjustmentComputation;
use App\Filament\Company\Resources\AdjustmentResource\Pages;
use App\Models\Accounting\Adjustment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AdjustmentResource extends Resource
{
    protected static ?string $model = Adjustment::class;

    // protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Form fields
                Select::make('company_id')
                    ->relationship('company', 'name')
                    ->required(),
                Select::make('account_id')
                    ->relationship('account', 'name')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description'),
                Select::make('category')
                    ->options([
                        'tax' => 'Tax',
                        'discount' => 'Discount',
                        'adjustment' => 'Adjustment',
                    ])
                    ->required(),
                Select::make('type')
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                    ])
                    ->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'reversed' => 'Reversed',
                    ])
                    ->default('pending')
                    ->required(),
                Select::make('recoverable')
                    ->options([
                        'yes' => 'Yes',
                        'no' => 'No',
                    ])
                    ->required(),
                TextInput::make('rate')->numeric()->required(),
                Select::make('computation')
                    ->label('Computation Type')
                    ->options([
                        AdjustmentComputation::Percentage->value => 'Percentage',
                        AdjustmentComputation::Fixed->value => 'Fixed',
                    ])
                    ->default(AdjustmentComputation::Fixed->value)  // Optional: set a default value
                    ->required()  // Optional: make it required
                    ->reactive(),  // Optional: if you want to react to changes
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->required(),
                TextInput::make('transaction_id')
                    ->nullable(),
                // ->numeric(),
                TextInput::make('previous_quantity')
                    ->nullable()
                    ->numeric(),
                TextInput::make('new_quantity')
                    ->nullable()
                    ->numeric(),
                TextInput::make('previous_price')
                    ->nullable()
                    ->numeric(),
                TextInput::make('new_price')
                    ->nullable()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company.name')->label('Company')->sortable(),
                TextColumn::make('account.name')->label('Account')->sortable(),
                TextColumn::make('name')->sortable(),
                TextColumn::make('category')->sortable(),
                TextColumn::make('type')->sortable(),
                TextColumn::make('status')->sortable(),
                TextColumn::make('created_at')->label('Created At')->date(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'reversed' => 'Reversed',
                    ])
                    ->label('Status'),
                SelectFilter::make('category')
                    ->options([
                        'tax' => 'Tax',
                        'discount' => 'Discount',
                        'adjustment' => 'Adjustment',
                    ])
                    ->label('Category'),
                SelectFilter::make('type')
                    ->options([
                        'sales' => 'Sales',
                        'purchase' => 'Purchase',
                    ])
                    ->label('Type'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                ]),
                Tables\Actions\BulkAction::make('approve')
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            $record->update(['status' => 'approved']);
                        }
                    })
                    ->label('Approve Selected'),

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
            'index' => Pages\ListAdjustments::route('/'),
            'create' => Pages\CreateAdjustment::route('/create'),
            'edit' => Pages\EditAdjustment::route('/{record}/edit'),
        ];
    }
}
