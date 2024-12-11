<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    //
    // public function users()
    // {
    //     return $this->belongsToMany(User::class);
    // }
    public function customUsers()
    {
        // Define your custom logic here
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }

    // Define the relationship to the company (tenant)
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user'); // Make sure 'role_user' is the correct table name
    }
}
