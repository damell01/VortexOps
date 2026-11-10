<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Set your password · VortexOps</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f5f3ff;color:#0f172a;font:16px/1.5 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;padding:20px}main{width:100%;max-width:440px;background:white;border:1px solid #e9e5ff;border-radius:20px;padding:28px;box-shadow:0 16px 45px #4c1d950f}h1{font-size:24px;margin:0 0 8px}p{color:#475569;margin:0 0 20px}.who{display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:12px;background:#f5f3ff;margin-bottom:6px}.who b{display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:#7c3aed;color:#fff;flex:none}.who span{font-size:14px;color:#334155;overflow-wrap:anywhere}label{display:block;font-weight:600;margin-top:16px}input{display:block;width:100%;min-height:48px;border:1px solid #94a3b8;border-radius:10px;padding:10px;margin-top:6px;font:inherit}button,a.btn{display:block;text-align:center;text-decoration:none;width:100%;min-height:48px;line-height:48px;border:0;border-radius:10px;background:#7c3aed;color:white;font:inherit;font-weight:600;margin-top:24px;cursor:pointer}:focus-visible{outline:3px solid #a78bfa;outline-offset:3px}.error{color:#b91c1c;font-size:14px;margin-top:8px}.hint{font-size:13px;color:#64748b;margin-top:6px}
    </style>
</head>
<body>
<main>
    @if ($used)
        <h1>This invite has been used</h1>
        <p>A password has already been set for this account, or a newer invite replaced this one. Sign in with your email and password, or use “Forgot password” on the sign-in page.</p>
        <a class="btn" href="{{ url('/admin/login') }}">Go to sign in</a>
    @else
        <h1>Welcome to VortexOps</h1>
        <p>Choose a password to finish setting up your account.</p>
        <div class="who"><b aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</b><span><strong>{{ $user->name }}</strong><br>{{ $user->email }}</span></div>
        <form method="post" action="{{ $action }}">
            @csrf
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required autofocus @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            <div class="hint">At least 8 characters.</div>
            @error('password')<div id="password-error" class="error" role="alert">{{ $message }}</div>@enderror
            <label for="password-confirmation">Confirm password</label>
            <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="255" required>
            <button type="submit">Set password and sign in</button>
        </form>
    @endif
</main>
</body>
</html>
