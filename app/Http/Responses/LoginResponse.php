<?php

namespace App\Http\Responses;

use App\Enums\Role;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false]);
        }

        $default = match ($request->user()->role) {
            Role::Admin => route('admin'),
            Role::Ap => route('ap'),
            Role::Trainer => route('trainer'),
        };

        return redirect()->intended($default);
    }
}
