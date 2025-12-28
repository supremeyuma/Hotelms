<?php
// app/Policies/LaundryOrderPolicy.php

namespace App\Policies;

use App\Models\LaundryOrder;
use App\Models\User;

class LaundryOrderPolicy
{
    public function updateStatus(User $user, LaundryOrder $order)
    {
        return $user->hasRole('laundry_staff'); // adjust per your role system
    }

    public function view(User $user, LaundryOrder $order)
    {
        return $user->hasAnyRole(['laundry_staff', 'frontdesk']);
    }
}
