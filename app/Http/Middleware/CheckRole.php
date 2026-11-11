<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // TODO: ganti dengan Eloquent / Laravel Auth
        $user = $request->session()->get('user');

        if (! $user || ! in_array($user['role'], $roles)) {
            abort(403, 'Akses ditolak.');
        }

        return $next($request);
    }
}
