<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $request->session()->get('locale', config('app.locale'));
        if (in_array($locale, config('family.locales'), true)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
