@extends('layouts.static')
@section('title', __('pages.terms_title'))

@section('content')
<h1 class="text-3xl font-semibold mb-6">{{ __('pages.terms_title') }}</h1>
<p class="text-slate-300 mb-4">{{ __('pages.terms_p1') }}</p>

<ul class="list-disc pl-6 text-slate-300 space-y-2">
  <li>{{ __('pages.terms_l1') }}</li>
  <li>{{ __('pages.terms_l2') }}</li>
  <li>{{ __('pages.terms_l3') }}</li>
  <li>{{ __('pages.terms_l4') }}</li>
</ul>
@endsection