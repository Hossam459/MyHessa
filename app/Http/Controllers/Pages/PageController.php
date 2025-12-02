<?php

namespace App\Http\Controllers\Pages;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use Illuminate\Http\JsonResponse;
class PageController extends Controller
{
    use HttpResponses;

    public function about(): JsonResponse
    {
        return $this->success([
            'about'=>'<p>Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint.</p>\n<p>Velit officia consequat duis enim velit mollit. Exercitation veniam consequat sunt nostrud amet.</p>'
        ]);
    }

    public function termsAndCondition(): JsonResponse
    {
        return $this->success([
            'terms_conditions'=>'<p>Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint.</p>\n<p>Velit officia consequat duis enim velit mollit. Exercitation veniam consequat sunt nostrud amet.</p>'
        ]);
    }


    public function privacyPolicy(): JsonResponse
    {
        return $this->success([
            'privacy_policy'=>'<p>Amet minim mollit non deserunt ullamco est sit aliqua dolor do amet sint.</p>\n<p>Velit officia consequat duis enim velit mollit. Exercitation veniam consequat sunt nostrud amet.</p>'
        ]);
    }
}