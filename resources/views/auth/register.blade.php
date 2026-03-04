<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>AI-IBL — Login</title>

   {{-- with Vite --}}
    @vite('resources/css/app.css')  
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet"/>

  <style>
    :root {
      --burnt-orange: #CC5500;
      --burnt-orange-dark: #A84400;
      --burnt-orange-light: #E8722E;
      --burnt-orange-muted: rgba(204, 85, 0, 0.08);
    }

    /* ── Theme tokens ── */
    [data-bs-theme="light"], :root {
      --bg-page: #F7F4F0;
      --bg-card: #FFFFFF;
      --text-muted-custom: #888;
      --border-color: #E5E0D8;
      --input-bg: #FAFAF8;
    }
    [data-bs-theme="dark"] {
      --bg-page: #111010;
      --bg-card: #1C1B1A;
      --text-muted-custom: #666;
      --border-color: #2E2C2A;
      --input-bg: #141312;
    }

    * { box-sizing: border-box; }

    body {
      font-family: 'DM Sans', sans-serif;
      background-color: var(--bg-page);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      transition: background-color 0.3s ease;
    }

    /* ── Accent stripe ── */
    body::before {
      content: '';
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--burnt-orange), var(--burnt-orange-light));
      z-index: 999;
    }

    /* ── Card ── */
    .login-card {
      background: var(--bg-card);
      border: 1px solid var(--border-color);
      border-radius: 16px;
      padding: 2rem 1.75rem;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 4px 40px rgba(0,0,0,0.06);
      animation: fadeUp 0.5s ease both;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(18px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Brand ── */
    .brand-mark {
      width: 44px; height: 44px;
      background: var(--burnt-orange);
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 1.5rem;
    }
    .brand-mark i { color: #fff; font-size: 1.25rem; }

    .brand-name {
      font-family: 'DM Serif Display', serif;
      font-size: 1.65rem;
      letter-spacing: -0.02em;
      color: var(--burnt-orange);
      margin: 0 0 0.2rem;
      line-height: 1;
    }
    .brand-tagline {
      font-size: 0.8rem;
      font-weight: 300;
      color: var(--text-muted-custom);
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 2.2rem;
    }

    /* ── Form ── */
    .form-label {
      font-size: 0.78rem;
      font-weight: 500;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--text-muted-custom);
      margin-bottom: 0.4rem;
    }

    .form-control {
      background-color: var(--input-bg) !important;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 0.65rem 0.9rem;
      font-size: 0.9rem;
      font-family: 'DM Sans', sans-serif;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .form-control:focus {
      border-color: var(--burnt-orange);
      box-shadow: 0 0 0 3px rgba(204, 85, 0, 0.12);
      background-color: var(--input-bg) !important;
    }

    /* Password wrapper */
    .pw-wrapper { position: relative; }
    .pw-toggle {
      position: absolute; right: 0.75rem; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; padding: 0;
      color: var(--text-muted-custom);
      cursor: pointer; font-size: 1rem;
      transition: color 0.2s;
    }
    .pw-toggle:hover { color: var(--burnt-orange); }

    /* Forgot link */
    .forgot-link {
      font-size: 0.78rem;
      color: var(--burnt-orange);
      text-decoration: none;
      font-weight: 500;
      transition: opacity 0.2s;
    }
    .forgot-link:hover { opacity: 0.75; color: var(--burnt-orange); }

    /* Submit button */
    .btn-signin {
      background: var(--burnt-orange);
      border: none;
      border-radius: 8px;
      color: #fff;
      font-family: 'DM Sans', sans-serif;
      font-weight: 500;
      font-size: 0.9rem;
      letter-spacing: 0.02em;
      padding: 0.7rem;
      width: 100%;
      margin-top: 0.5rem;
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }
    .btn-signin:hover {
      background: var(--burnt-orange-dark);
      box-shadow: 0 6px 20px rgba(204,85,0,0.3);
      transform: translateY(-1px);
      color: #fff;
    }
    .btn-signin:active { transform: translateY(0); }

    /* Divider */
    .divider {
      display: flex; align-items: center; gap: 0.75rem;
      color: var(--text-muted-custom);
      font-size: 0.75rem;
      margin: 1.5rem 0;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1;
      height: 1px; background: var(--border-color);
    }

    /* SSO button */
    .btn-sso {
      background: transparent;
      border: 1px solid var(--border-color);
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.85rem;
      font-weight: 400;
      width: 100%;
      padding: 0.65rem;
      display: flex; align-items: center; justify-content: center; gap: 0.5rem;
      transition: border-color 0.2s, background 0.2s;
    }
    .btn-sso:hover {
      border-color: var(--burnt-orange);
      background: var(--burnt-orange-muted);
    }

    /* Footer */
    .login-footer {
      text-align: center;
      font-size: 0.78rem;
      color: var(--text-muted-custom);
      margin-top: 1.75rem;
    }
    .login-footer a {
      color: var(--burnt-orange);
      text-decoration: none;
      font-weight: 500;
    }
    .login-footer a:hover { text-decoration: underline; }

    /* Remember me */
    .form-check-input:checked {
      background-color: var(--burnt-orange);
      border-color: var(--burnt-orange);
    }
    .form-check-input:focus {
      box-shadow: 0 0 0 3px rgba(204,85,0,0.12);
      border-color: var(--burnt-orange);
    }
    .form-check-label {
      font-size: 0.82rem;
      color: var(--text-muted-custom);
    }
  </style>
</head>
<body>

  <div class="login-card">

    <!-- Brand -->
    <div class="brand-mark">
      <i class="bi bi-cpu"></i>
    </div>
    <h1 class="brand-name">AI-IBL</h1>
    <p class="brand-tagline">Artificial Intelligence Inquiry-Based Learning</p>
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <!-- Form -->
    <form action="{{ route('register.post') }}" method="post">
      @csrf
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" id="username" name="username" class="form-control" placeholder="username" autocomplete="username" value="{{ old('username') }}" />
      </div>

      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label for="password" class="form-label mb-0">Password</label>
        </div>
        <div class="pw-wrapper">
          <input type="password" id="password" name="password" class="form-control pe-5" placeholder="password" autocomplete="current-password" />
        </div>
      </div>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <label for="password_confirmation" class="form-label mb-0">Confirm Password</label>
        </div>
        <div class="pw-wrapper">
          <input type="password" id="password_confirmation" name="password_confirmation" class="form-control pe-5" placeholder="confirm password" autocomplete="current-password" />
        </div>
      </div>


      <button type="submit" class="btn btn-signin">Sign in</button>
    </form>

    <!-- Footer -->
    <p class="login-footer">
      Already have an account? <a href="{{ route('login') }}">Sign in</a>
    </p>

  </div>

  @vite('resources/js/app.js')
  <script>
    // Bootstrap 5.3 auto theme: respects prefers-color-scheme automatically via data-bs-theme="auto"

    // Password toggle
    const pwToggle = document.getElementById('pwToggle');
    const pwInput  = document.getElementById('password');
    const pwIcon   = document.getElementById('pwIcon');
    pwToggle.addEventListener('click', () => {
      const show = pwInput.type === 'password';
      pwInput.type = show ? 'text' : 'password';
      pwIcon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  </script>
</body>
</html>