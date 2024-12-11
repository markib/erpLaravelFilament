<?php

namespace App\Filament\Company\Resources;

use App\Filament\Company\Resources\RolesResource\Pages;
use App\Filament\Company\Resources\RolesResource\RelationManagers;
use App\Models\Role;
use App\Models\Roles;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class RolesResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Role::class;
    

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

   


    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create_role',
            'update',
            'delete',
            'delete_any',
        ];
    }
    public static function canViewAny(): bool
    {
        // return true;
        $user = auth()->user();
        $user->hasRole('editor');
        dd($user);
        $user->load('roles.permissions');  // Ensure roles and permissions are loaded

        $tenantId = request()->route('tenant');
        $companyId = $user->currentCompany->id;

        // // Log the permission check to ensure it's working
        // Log::info('Permission check result: ', [
        //     'has_permission' => $user->hasPermissionTo('view_any_role'),
        //     'company_id_match' => $companyId === $tenantId,
        // ]);

        return $user->hasPermissionTo('view_any_role') && $companyId === $tenantId;
    }

    // public static function canCreateRole(): bool
    // {
    //     // This checks the 'create_role' permission from the policy
    //     $user = auth()->user();
    //     $user->load('roles.permissions');  // Ensure roles and permissions are loaded

    //     $tenantId = (int)request()->route('tenant');
    //     $companyId = $user->currentCompany->id;

    //     // Log the permission check to ensure it's working
    //     Log::info('Permission check to create role: ', [
    //         'has_permission' => $user->hasPermissionTo('view_any_role'),
    //         'company_id_match' => $companyId === $tenantId,
    //     ]);

    //     return $user->hasPermissionTo('create_role') && $companyId === $tenantId;
    // }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
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
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRoles::route('/create'),
            'edit' => Pages\EditRoles::route('/{record}/edit'),
        ];
    }
}
