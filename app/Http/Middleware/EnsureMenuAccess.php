<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuKey): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Akun Anda tidak aktif atau tidak memiliki sesi valid.');
        }

        if (! $user->hasMenu($menuKey)) {
            abort(403, 'Anda tidak memiliki hak akses ke menu ini. Hubungi Administrator.');
        }

        return $next($request);
    }
}