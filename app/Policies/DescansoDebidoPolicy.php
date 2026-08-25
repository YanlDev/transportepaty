<?php

namespace App\Policies;

use App\Models\User;

class DescansoDebidoPolicy
{
    public function update(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
