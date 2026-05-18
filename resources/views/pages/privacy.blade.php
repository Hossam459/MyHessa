@extends('layouts.static')
@section('title', __('pages.privacy_title'))

@section('content')
<h1 class="text-3xl font-semibold mb-6">{{ __('pages.privacy_title') }}</h1>
<p class="text-slate-300 mb-4">{{ __('pages.privacy_p1') }}</p>
<p class="text-slate-300 mb-4">{{ __('pages.privacy_p2') }}</p>
<p class="text-slate-300">{{ __('pages.privacy_p3') }}</p>
@endsection
