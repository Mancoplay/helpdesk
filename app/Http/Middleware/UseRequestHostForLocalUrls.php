<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class UseRequestHostForLocalUrls
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local')) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());
            URL::forceScheme($request->getScheme());
        }

        return $next($request);
    }
}