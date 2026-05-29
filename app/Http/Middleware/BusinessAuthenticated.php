<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('business')->check()) {
            return redirect()->route('business.login');
        }

        return $next($request);
    }
}
