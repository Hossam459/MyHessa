<?php

namespace App\Http\Controllers\Web;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

class LandingController extends Controller
{
    public function index(Request $request){
   if(in_array($request->lang, ['ar','en'])){
     session(['app_locale'=>$request->lang]);
     app()->setLocale($request->lang);
    }
  return view('landing');
}
   
}
