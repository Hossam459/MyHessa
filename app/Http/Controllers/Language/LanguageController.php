<?php

namespace App\Http\Controllers\Language;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Http\Traits\Access;

class LanguageController extends Controller
{
    use HttpResponses, Access;

    public function changeLang(string $lang)
    {
        if (! in_array($lang, ['en', 'ar'])) {
            return $this->error('Invalid language', 422);
        }
        app()->setLocale($lang);
        return $this->success(null,__('messages.change_lang'));
    }

}
