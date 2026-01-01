<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use Illuminate\Http\JsonResponse;
use App\Http\Traits\Access;

class PageController extends Controller
{
    use HttpResponses, Access;

    public function about(): JsonResponse
    {
        return $this->success(
            __('messages.about_us')
        );
    }

    public function termsAndCondition(): JsonResponse
    {
        return $this->success(
            __('messages.terms_conditions')
        );
    }


    public function privacyPolicy(): JsonResponse
    {
        return $this->success(
            __('messages.privacy_policy')
        );
    }
}