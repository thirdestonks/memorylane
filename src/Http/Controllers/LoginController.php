<?php

namespace Thirdestonks\MemoryLane\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Thirdestonks\MemoryLane\Http\Middleware\Authenticate;

class LoginController extends Controller
{
    public function show(Request $request)
    {
        if (Authenticate::loggedIn($request)) {
            return redirect()->route('memorylane.index');
        }

        // Without credentials the page shows setup steps instead of a form.
        return view('memorylane::login', ['setup' => ! Authenticate::loginEnabled()]);
    }

    public function login(Request $request)
    {
        abort_unless(Authenticate::loginEnabled(), 404);

        // hash_equals: constant-time, so response timing can't leak the key.
        $valid = hash_equals((string) config('memorylane.username'), (string) $request->input('username'))
            & hash_equals((string) config('memorylane.password'), (string) $request->input('password'));

        if (! $valid) {
            return back()->withErrors(['login' => 'Wrong username or password.'])->onlyInput('username');
        }

        $request->session()->regenerate();
        $request->session()->put('memorylane.login', Authenticate::fingerprint());

        return redirect()->route('memorylane.index');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('memorylane.login');

        return redirect()->route('memorylane.login');
    }
}
