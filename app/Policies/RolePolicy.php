<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        
        $user = auth()->user();
       
        $user->load('roles.permissions');  // Ensure roles and permissions are loaded
        $tenantId = (int)request()->route('tenant');
        $company_id = $user->currentCompany->id;
        // Log the permission check to ensure it's working
        Log::info('Checking if user can view any roles:', [
            'user_permissions' => $user->permissions->pluck('name'),
            'tenant_id' => $tenantId,
            'company_id' => $company_id,
            'permission_check' => $user->hasPermissionTo('view_any_role'),
        ]);
        return $user->hasPermissionTo('view_any_role') && $company_id === $tenantId;
    }

    

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Role $role): bool
    {
        return $user->can('view_role');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        $user = auth()->user();
        $user->load('roles.permissions');  // Ensure roles and permissions are loaded

        $tenantId = (int)request()->route('tenant');
        $companyId = $user->currentCompany->id;

        // Log the permission check to ensure it's working
        Log::info('Permission check to create role: ', [
            'has_permission' => $user->hasPermissionTo('create_role'),
            'company_id_match' => $companyId === $tenantId,
        ]);

        return $user->hasPermissionTo('create_role') && $companyId === $tenantId;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can('update_role');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('delete_role');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_role');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->can('{{ ForceDelete }}');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('{{ ForceDeleteAny }}');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->can('{{ Restore }}');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('{{ RestoreAny }}');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Role $role): bool
    {
        return $user->can('{{ Replicate }}');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('{{ Reorder }}');
    }
}
