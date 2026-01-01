<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class ChangeLang
{
 public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language');
        $locale = $locale ? substr($locale, 0, 2) : null;
        if (! in_array($locale, ['ar', 'en'])) {
            $locale = config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
