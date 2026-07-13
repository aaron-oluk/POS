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
  @if ($settings->self_checkout_enabled && ! $errors->any())
  <div class="login-card" id="choiceView" style="text-align:center;">
    <div class="login-logo">
      <div class="sidebar-logo">N</div>
      <h1>{{ config('app.name') }}</h1>
      <p>{{ $settings->store_name }}</p>
    </div>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button type="button" class="btn btn-primary btn-lg" id="showStaffLoginBtn"><i class="bx bxs-log-in"></i> Staff Sign In</button>
      <a href="{{ route('self-checkout.index') }}" class="btn btn-secondary btn-lg"><i class="bx bx-scan"></i> Self-Checkout</a>
    </div>
  </div>
  @endif

  <div class="login-card" id="formView" style="{{ $settings->self_checkout_enabled && ! $errors->any() ? 'display:none;' : '' }}">
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
    <button type="button" class="btn btn-secondary btn-lg" id="backToChoiceBtn" style="width:100%;justify-content:center;margin-top:10px;">
      <i class="bx bx-arrow-back"></i> Back
    </button>
    @endif

    <div class="login-hint">Demo: sarah@nexuscoffee.com / password</div>
  </div>
</div>
<script>
  document.getElementById('showStaffLoginBtn')?.addEventListener('click', () => {
    document.getElementById('choiceView').style.display = 'none';
    document.getElementById('formView').style.display = '';
    document.getElementById('email').focus();
  });
  document.getElementById('backToChoiceBtn')?.addEventListener('click', () => {
    document.getElementById('formView').style.display = 'none';
    document.getElementById('choiceView').style.display = '';
  });
</script>
</body>
</html>
