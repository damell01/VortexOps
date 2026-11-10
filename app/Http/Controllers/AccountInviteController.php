<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AccountInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** The invite link: choose a password, then straight into VortexOps. */
class AccountInviteController extends Controller
{
    public function show(Request $request, User $user)
    {
        if (! AccountInvite::current($user, $request->query('v'))) {
            return response()->view('auth.account-invite', ['user' => $user, 'used' => true], 410);
        }

        return view('auth.account-invite', ['user' => $user, 'used' => false, 'action' => $request->fullUrl()]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless(AccountInvite::current($user, $request->query('v')), 410, 'This invite link has already been used.');
        $data = $request->validate(['password' => ['required', 'string', 'min:8', 'max:255', 'confirmed']]);

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', $user->password);

        return redirect('/admin');
    }
}
