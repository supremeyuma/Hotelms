<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;

class StaffActionService
{
    public function verifyCode(string $code): bool
    {
        return Hash::check($code, auth()->user()->staffProfile->action_code_hash);
    }
}
