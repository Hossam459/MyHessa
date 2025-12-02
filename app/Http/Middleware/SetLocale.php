<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language');

        // Extract the primary language from the header (e.g., "en-US,en;q=0.9" -> "en")
        if ($locale) {
            $locale = explode(',', $locale)[0];
            $locale = explode('-', $locale)[0];
        }

        // Set a default locale if Accept-Language is not provided or invalid
        if (! in_array($locale, ['en', 'es', 'fr'])) { // Adjust supported locales
            $locale = config('app.locale'); // Use the default app locale
        }

        App::setLocale($locale);

        return $next($request);
    }
}