@extends('layouts.static')
@section('title', __('pages.about_title'))

@section('content')
<h1 class="text-3xl font-semibold mb-6">{{ __('pages.about_title') }}</h1>
<p class="text-slate-300 mb-4">{{ __('pages.about_p1') }}</p>
<p class="text-slate-300 mb-4">{{ __('pages.about_p2') }}</p>
<p class="text-slate-300">{{ __('pages.about_p3') }}</p>
@endsection