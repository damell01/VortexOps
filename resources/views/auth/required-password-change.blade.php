<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change your password · VortexOps</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#0f172a;font:16px/1.5 system-ui,sans-serif;min-height:100vh;display:grid;place-items:center;padding:20px}main{width:100%;max-width:440px;background:white;border:1px solid #e2e8f0;border-radius:20px;padding:28px;box-shadow:0 16px 45px #0f172a0d}h1{font-size:24px;margin:0 0 8px}p{color:#475569;margin:0 0 24px}label{display:block;font-weight:600;margin-top:18px}input{display:block;width:100%;min-height:48px;border:1px solid #94a3b8;border-radius:10px;padding:10px;margin-top:6px;font:inherit}button{width:100%;min-height:48px;border:0;border-radius:10px;background:#4f46e5;color:white;font:inherit;font-weight:600;margin-top:24px;cursor:pointer}:focus-visible{outline:3px solid #818cf8;outline-offset:3px}.error{color:#b91c1c;font-size:14px;margin-top:8px}
    </style>
</head>
<body>
<main>
    <h1>Choose your own password</h1>
    <p>You must replace your initial password before using VortexOps. Use at least 8 characters.</p>
    <form method="post" action="{{ route('account.password.update') }}">
        @csrf
        <label for="password">New password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="255" required autofocus @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
        @error('password')<div id="password-error" class="error" role="alert">{{ $message }}</div>@enderror
        <label for="password-confirmation">Confirm new password</label>
        <input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="255" required>
        <button type="submit">Save password and continue</button>
    </form>
</main>
</body>
</html>
