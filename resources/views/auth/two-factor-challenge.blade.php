<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication — VortexOps</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #0a0e1a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 25px 50px rgb(0 0 0 / .5);
        }
        .logo {
            font-size: 1.375rem;
            font-weight: 800;
            letter-spacing: -0.03em;
            background: linear-gradient(135deg, #fff 0%, #c4b5fd 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.25rem;
        }
        .subtitle { font-size: 0.8125rem; color: rgba(255,255,255,.45); margin-bottom: 2rem; }
        h1 { font-size: 1.0625rem; font-weight: 600; color: #fff; margin-bottom: 0.375rem; }
        p { font-size: 0.8125rem; color: rgba(255,255,255,.5); margin-bottom: 1.5rem; line-height: 1.6; }
        label { display: block; font-size: 0.75rem; font-weight: 500; color: rgba(255,255,255,.6); margin-bottom: 0.375rem; letter-spacing: 0.02em; }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 0.6875rem 0.875rem;
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.12);
            border-radius: 8px;
            color: #fff;
            font-size: 1.125rem;
            letter-spacing: 0.15em;
            text-align: center;
            outline: none;
            transition: border-color .15s;
            appearance: textfield;
        }
        input[type="text"]:focus, input[type="number"]:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgb(124 58 237 / .2);
        }
        button[type="submit"] {
            width: 100%;
            margin-top: 1.25rem;
            padding: 0.75rem;
            background: #7c3aed;
            color: #fff;
            font-weight: 600;
            font-size: 0.875rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity .15s;
        }
        button[type="submit"]:hover { opacity: .9; }
        .toggle-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            font-size: 0.8125rem;
            color: #a78bfa;
            cursor: pointer;
            background: none;
            border: none;
            text-decoration: underline;
            text-underline-offset: 3px;
        }
        .error {
            background: rgb(239 68 68 / .12);
            border: 1px solid rgb(239 68 68 / .3);
            border-radius: 8px;
            padding: 0.625rem 0.875rem;
            font-size: 0.8125rem;
            color: #fca5a5;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">VortexOps</div>
        <div class="subtitle">Two-factor authentication</div>

        <h1 id="form-title">Enter your code</h1>
        <p id="form-desc">Open your authenticator app and enter the 6-digit code for VortexOps.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/two-factor-challenge" id="totp-form">
            @csrf
            <div id="totp-section">
                <label for="code">Authentication Code</label>
                <input
                    type="text"
                    id="code"
                    name="code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    autofocus
                    placeholder="000000"
                >
            </div>

            <div id="recovery-section" style="display:none">
                <label for="recovery_code">Recovery Code</label>
                <input
                    type="text"
                    id="recovery_code"
                    name="recovery_code"
                    autocomplete="off"
                    placeholder="xxxx-xxxx-xxxx"
                    style="letter-spacing: 0.05em; font-size: 0.9375rem;"
                >
            </div>

            <button type="submit">Verify &amp; Continue</button>
        </form>

        <button class="toggle-link" id="toggle-btn" type="button">
            Use a recovery code instead
        </button>
    </div>

    <script>
        const totpSection     = document.getElementById('totp-section');
        const recoverySection = document.getElementById('recovery-section');
        const toggleBtn       = document.getElementById('toggle-btn');
        const title           = document.getElementById('form-title');
        const desc            = document.getElementById('form-desc');
        let usingRecovery     = false;

        toggleBtn.addEventListener('click', () => {
            usingRecovery = !usingRecovery;
            totpSection.style.display     = usingRecovery ? 'none' : 'block';
            recoverySection.style.display = usingRecovery ? 'block' : 'none';
            title.textContent   = usingRecovery ? 'Enter a recovery code' : 'Enter your code';
            desc.textContent    = usingRecovery
                ? 'Use one of the recovery codes you saved when you enabled 2FA.'
                : 'Open your authenticator app and enter the 6-digit code for VortexOps.';
            toggleBtn.textContent = usingRecovery ? 'Use authenticator app instead' : 'Use a recovery code instead';
            const input = usingRecovery
                ? document.getElementById('recovery_code')
                : document.getElementById('code');
            input.focus();
        });
    </script>
</body>
</html>
