<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public demo only: visitors cannot upload files to the server. */
class DemoGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('family.demo_mode') && $request->allFiles()) {
            return back()->with('error', __('Kupakia faili kumezimwa kwenye demo.'));
        }

        return $next($request);
    }
}
