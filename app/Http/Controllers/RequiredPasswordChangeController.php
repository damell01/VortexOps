<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequiredPasswordChangeController extends Controller
{
    public function show(Request $request)
    {
        return $request->user()->must_change_password
            ? view('auth.required-password-change')
            : redirect('/admin');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']]);
        if ($data['password'] === 'password123!' || Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a new password different from your initial password.']);
        }
        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $request->user()->password);

        return redirect('/admin');
    }
}
