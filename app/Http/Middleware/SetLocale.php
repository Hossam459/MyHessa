<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $lang = $request->header('Accept-Language');

        if (!in_array($lang, ['ar', 'en'])) {
            $lang = 'en'; // default
        }

        App::setLocale($lang);

        return $next($request);
    }
}
