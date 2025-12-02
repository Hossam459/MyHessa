<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class SetLocale
{
    protected $supportedLocales = ['ar', 'en'];

    public function handle($request, Closure $next)
    {       
         $acceptLang = $request->header('Accept-Language', '');

        $locale = 'ar';

        if (!empty($acceptLang)) {
            $languages = explode(',', $acceptLang);

            foreach ($languages as $lang) {
                $code = substr(trim($lang), 0, 2);
                if (in_array($code, $this->supportedLocales)) {
                    $locale = $code;
                    break;
                }
            }
        }

        App::setLocale($locale);

        Log::info("Using Locale: " . App::getLocale());

        return $next($request);
    }
}