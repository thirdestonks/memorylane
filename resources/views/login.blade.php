@extends('memorylane::layout')

@section('title', 'Sign in')

@section('content')
    <form method="post" action="{{ route('memorylane.login') }}" class="panel" style="max-width:380px;margin:40px auto;padding:24px">
        @csrf
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin:0 0 4px">
            <h1 class="cap" style="font-size:16px;margin:0">Sign in</h1>
            <span style="display:flex;align-items:center;gap:8px"><img class="sprite" src="{{ \Thirdestonks\MemoryLane\Http\Controllers\DashboardController::sprite('hi') }}" alt="" width="48" height="48"><span class="stamp tilt warn">Restricted</span></span>
        </div>
        <hr style="border:0;border-top:2px solid var(--fg);margin:10px 0 12px">

        @if ($setup)
            <p style="margin:0 0 12px">No login is set up yet, so the dashboard stays locked. Add these to this app's <code>.env</code>:</p>
            <pre style="padding:12px;margin:0 0 12px;background:var(--bg);border:1px solid var(--line);border-radius:3px">MEMORYLANE_USERNAME=you
MEMORYLANE_PASSWORD=a-long-random-key</pre>
            <p class="dim" style="margin:0">Make a key with <code>php -r "echo bin2hex(random_bytes(24));"</code>, then reload this page.</p>
        @else
            <p class="dim" style="margin:0 0 18px">Use the MemoryLane credentials from this app's <code>.env</code>.</p>

            @error('login')
                <p class="slow" style="margin:0 0 14px;font-weight:700">{{ $message }}</p>
            @enderror

            <label class="field">Username
                <input name="username" value="{{ old('username') }}" autocomplete="username" required autofocus>
            </label>
            <label class="field">Password
                <input name="password" type="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="primary">Sign in</button>
        @endif
    </form>
@endsection
