<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every protected page re-checks that the session's user is still usable:
 * verified phone, account enabled, membership active/suspended. Disabling a
 * member therefore takes effect on their very next request.
 */
class EnsureActiveMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->isUsable()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('auth.account_unavailable'));
        }

        if (! $user->last_active_at || $user->last_active_at->lt(now()->subMinutes(5))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        if (! $user->member->whatsapp_opt_in_at && ! $request->routeIs('welcome.*', 'logout', 'locale', 'profile.*')) {
            return redirect()->route('welcome.show');
        }

        return $next($request);
    }
}
