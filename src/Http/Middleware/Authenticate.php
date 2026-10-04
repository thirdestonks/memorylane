<?php

namespace Thirdestonks\MemoryLane\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        // Login everywhere, local included. A host-defined viewMemoryLane gate can also let its own users in.
        if (self::loggedIn($request) || Gate::allows('viewMemoryLane')) {
            return $next($request);
        }

        // No credentials set yet: the login page explains how to add them. No metrics either way.
        return redirect()->route('memorylane.login');
    }

    public static function loginEnabled(): bool
    {
        return filled(config('memorylane.username')) && filled(config('memorylane.password'));
    }

    // Hash of the current credentials. Changing either one in .env logs every session out.
    public static function fingerprint(): string
    {
        return hash('sha256', config('memorylane.username').'|'.config('memorylane.password'));
    }

    public static function loggedIn(Request $request): bool
    {
        return self::loginEnabled()
            && hash_equals(self::fingerprint(), (string) $request->session()->get('memorylane.login', ''));
    }
}
