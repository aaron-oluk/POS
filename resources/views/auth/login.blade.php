<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Sign in — {{ config('app.name') }}</title>
<link href="https://fonts.googleapis.com/css2?family=Figtree:ital,wght@0,300..900;1,300..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css">
@vite(['resources/css/app.css'])
</head>
<body>
<div class="bg-pattern"></div>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <div class="sidebar-logo">N</div>
      <h1>{{ config('app.name') }}</h1>
      <p>Sign in to your terminal</p>
    </div>

    @if ($errors->any())
      <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf
      <div class="input-group" style="margin-bottom:16px;">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" class="input-field" value="{{ old('email') }}" placeholder="you@nexuscoffee.com" required autofocus>
      </div>
      <div class="input-group" style="margin-bottom:20px;">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" class="input-field" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%;justify-content:center;">
        <i class="bx bxs-log-in"></i> Sign In
      </button>
    </form>

    @if ($settings->self_checkout_enabled)
    <div style="display:flex;align-items:center;gap:10px;margin:20px 0;">
      <div style="flex:1;height:1px;background:var(--border);"></div>
      <span style="font-size:11px;color:var(--fg-dim);">OR</span>
      <div style="flex:1;height:1px;background:var(--border);"></div>
    </div>
    <a href="{{ route('self-checkout.index') }}" class="btn btn-secondary btn-lg" style="width:100%;justify-content:center;">
      <i class="bx bx-scan"></i> Self-Checkout
    </a>
    @endif

    <div class="login-hint">Demo: sarah@nexuscoffee.com / password</div>
  </div>
</div>
</body>
</html>
