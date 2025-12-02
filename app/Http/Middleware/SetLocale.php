<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $lang = $request->header('Accept-Language', 'ar');

        if (!in_array($lang, ['ar', 'en'])) {
            $lang = 'en';
        }

        App::setLocale($lang);
        Log::info("Using Locale: " . App::getLocale());
        return $next($request);
    }
}
