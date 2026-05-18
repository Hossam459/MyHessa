<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale()=='ar'?'rtl':'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title') - MyHessa</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100">

<header class="border-b border-white/10 bg-slate-950/80">
  <div class="max-w-5xl mx-auto px-4 py-4 flex justify-between items-center">
    <a href="/" class="flex items-center gap-2">
      <img src="{{ asset('assets/logo.png') }}" class="w-8 h-8 rounded-lg">
      <span class="font-semibold">MyHessa</span>
    </a>
    <a href="/" class="text-sm text-slate-300 hover:text-white">Home</a>
  </div>
</header>

<main class="max-w-3xl mx-auto px-4 py-12">
  @yield('content')
</main>

<footer class="border-t border-white/10 text-center text-xs text-slate-400 py-6">
  © {{ date('Y') }} MyHessa. All rights reserved.
</footer>

</body>
</html>
