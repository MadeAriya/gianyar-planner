<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Super admin selalu lolos
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Akun suspended tidak boleh mengakses apapun
        if ($user->isSuspended()) {
            Auth::logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->with('error', 'Akun Anda sedang ditangguhkan. Silakan hubungi admin untuk bantuan.');
        }

        // Akun pending atau rejected hanya boleh melihat status pengajuan
        if (! $user->isActive()) {
            if (! $request->routeIs('status.pengajuan') && ! $request->routeIs('status.ajukan_ulang') && ! $request->routeIs('logout')) {
                return redirect()->route('status.pengajuan');
            }
        }

        return $next($request);
    }
}
