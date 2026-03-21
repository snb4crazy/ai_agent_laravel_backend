<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Validation\Rules;

class UserRegistrationRules
{
    /**
     * Get the validation rules for creating a user.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function make(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
    }
}
