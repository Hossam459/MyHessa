@php
  $isAr = app()->getLocale() === 'ar';
  $nextLang = $isAr ? 'en' : 'ar'  @endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ __('landing.app_name') }}</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/logo.png') }}">

  <!-- Tailwind CDN -->
  <script src="https://cdn.tailwindcss.com"></script>

  <style>
    .glass { backdrop-filter: blur(14px); }
    .grid-bg {
      background-image:
        radial-gradient(circle at 1px 1px, rgba(15,23,42,.08) 1px, transparent 0);
      background-size: 22px 22px;
    }
  </style>
</head>
<body class="bg-slate-950 text-slate-100">

  <!-- Top gradient background -->
  <div class="relative overflow-hidden">
    <div class="absolute inset-0">
      <div class="absolute -top-40 -left-40 w-[520px] h-[520px] rounded-full bg-[#7B61FF]/30 blur-3xl"></div>
      <div class="absolute -top-20 -right-40 w-[540px] h-[540px] rounded-full bg-amber-300/20 blur-3xl"></div>
      <div class="absolute top-0 left-0 right-0 h-full opacity-30 grid-bg"></div>
    </div>

    <!-- Header -->
    <header class="relative z-20 sticky top-0 border-b border-white/10 bg-slate-950/60 glass">
      <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
<img src="{{ asset('assets/logo.png') }}"
     alt="MyHessa Logo"
     class="w-10 h-10 rounded-2xl object-contain shadow-lg shadow-black/20" />
               <div class="leading-tight">
            <div class="font-semibold">{{ __('landing.app_name') }}</div>
            <div class="text-xs text-slate-300">{{ __('landing.tagline') }}</div>
          </div>
        </a>

        <nav class="hidden md:flex items-center gap-6 text-sm text-slate-300">
          <a href="#features" class="hover:text-white"> {{ __('landing.nav_features') }} </a>
          <a href="#how" class="hover:text-white"> {{ __('landing.nav_how') }} </a>
          <a href="#faq" class="hover:text-white"> {{ __('landing.nav_faq') }} </a>
        </nav>

        <div class="flex items-center gap-2">
          <!-- Switch Language (keeps current url + query) -->
          <a href="{{ request()->fullUrlWithQuery(['lang' => $nextLang]) }}"
             class="px-3 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm">
            {{ $isAr ? 'EN' : 'AR' }}
          </a>

          <a href="#download"
             class="hidden sm:inline-flex px-4 py-2 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-sm">
            {{ __('landing.download_app') }}
          </a>

          <a href="#download"
             class="inline-flex px-4 py-2 rounded-xl bg-[#7B61FF] hover:opacity-95 text-white text-sm shadow-lg shadow-[#7B61FF]/25">
            {{ __('landing.get_started') }}
          </a>
        </div>
      </div>
    </header>

    <!-- Hero -->
    <section class="relative z-10">
      <div class="max-w-6xl mx-auto px-4 pt-14 pb-10 md:pt-20 md:pb-16">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-white/10 bg-white/5 text-sm text-slate-200">
              <span class="w-2 h-2 rounded-full bg-[#7B61FF]"></span>
              {{ __('landing.tagline') }}
            </div>

            <h1 class="mt-5 text-4xl md:text-5xl font-semibold tracking-tight">
              {{ __('landing.hero_title_1') }}
              <span class="text-[#7B61FF]">{{ __('landing.hero_title_2') }}</span>
            </h1>

            <p class="mt-4 text-slate-300 text-lg leading-relaxed">
              {{ __('landing.hero_desc') }}
            </p>

            <div class="mt-8 flex flex-col sm:flex-row gap-3">
              <a href="#download"
                 class="px-5 py-3 rounded-2xl bg-[#7B61FF] hover:opacity-95 text-white font-medium text-center shadow-lg shadow-[#7B61FF]/25">
                {{ __('landing.hero_btn_download') }}
              </a>

              <a href="#how"
                 class="px-5 py-3 rounded-2xl border border-white/10 bg-white/5 hover:bg-white/10 font-medium text-center">
                {{ __('landing.hero_btn_how') }}
              </a>
            </div>

            <!-- Social proof -->
            <div class="mt-8 grid sm:grid-cols-3 gap-3">
              <div class="p-4 rounded-2xl border border-white/10 bg-white/5">
                <div class="text-xs text-slate-300">{{ __('landing.students') }}</div>
                <div class="mt-1 font-semibold text-white">Discover & Join</div>
              </div>
              <div class="p-4 rounded-2xl border border-white/10 bg-white/5">
                <div class="text-xs text-slate-300">{{ __('landing.teachers') }}</div>
                <div class="mt-1 font-semibold text-white">Manage & Teach</div>
              </div>
              <div class="p-4 rounded-2xl border border-white/10 bg-white/5">
                <div class="text-xs text-slate-300">Rating</div>
                <div class="mt-1 font-semibold text-white">⭐ 4.7+</div>
              </div>
            </div>
          </div>

          <!-- Right: “App Preview” device -->
          <div class="relative">
            <div class="absolute -inset-6 rounded-[36px] bg-gradient-to-br from-[#7B61FF]/25 to-white/5 blur-2xl"></div>

            <div class="relative rounded-[32px] border border-white/10 bg-slate-900/60 glass p-5 shadow-2xl">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-xs text-slate-300">MyHessa</div>
                  <div class="font-semibold">Group Feed</div>
                </div>
                <div class="text-xs px-2 py-1 rounded-lg border border-white/10 bg-white/5">Mobile</div>
              </div>

              <div class="mt-5 space-y-3">
                <div class="p-4 rounded-2xl border border-white/10 bg-white/5">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-white/10"></div>
                    <div class="flex-1">
                      <div class="h-3 w-32 bg-white/10 rounded"></div>
                      <div class="mt-2 h-3 w-48 bg-white/5 rounded"></div>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-lg bg-[#7B61FF]/20 text-[#CFC6FF] border border-[#7B61FF]/30">Pinned</span>
                  </div>
                  <div class="mt-4 h-3 w-full bg-white/5 rounded"></div>
                  <div class="mt-2 h-3 w-4/5 bg-white/5 rounded"></div>
                  <div class="mt-4 flex gap-2">
                    <span class="text-xs px-2 py-1 rounded-lg border border-white/10 bg-white/5">PDF</span>
                    <span class="text-xs px-2 py-1 rounded-lg border border-white/10 bg-white/5">Image</span>
                  </div>
                </div>

                <div class="p-4 rounded-2xl border border-white/10 bg-white/5">
                  <div class="flex items-center justify-between">
                    <div class="h-3 w-40 bg-white/10 rounded"></div>
                    <span class="text-xs px-2 py-1 rounded-lg bg-green-500/15 text-green-200 border border-green-500/20">Attendance Open</span>
                  </div>
                  <div class="mt-3 h-3 w-3/4 bg-white/5 rounded"></div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                  <div class="p-3 rounded-2xl border border-white/10 bg-white/5">
                    <div class="text-xs text-slate-300">Attendance</div>
                    <div class="mt-1 font-semibold">Auto</div>
                  </div>
                  <div class="p-3 rounded-2xl border border-white/10 bg-white/5">
                    <div class="text-xs text-slate-300">Ratings</div>
                    <div class="mt-1 font-semibold">⭐ 4.7</div>
                  </div>
                  <div class="p-3 rounded-2xl border border-white/10 bg-white/5">
                    <div class="text-xs text-slate-300">Payments</div>
                    <div class="mt-1 font-semibold">Soon</div>
                  </div>
                </div>
              </div>

              <div class="mt-5 text-xs text-slate-300">
                * Preview is a UX mock. Final screens will match your mobile UI.
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Logos strip (optional) -->
    <section class="relative z-10">
      <div class="max-w-6xl mx-auto px-4 pb-12">
        <div class="flex flex-wrap items-center justify-center gap-3">
          @foreach (['Attendance','Groups','Materials','Ratings','Scheduling'] as $chip)
            <span class="text-xs px-3 py-2 rounded-full border border-white/10 bg-white/5 text-slate-200">{{ $chip }}</span>
          @endforeach
        </div>
      </div>
    </section>
  </div>

  <!-- Features -->
  <section id="features" class="bg-slate-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
        <div>
          <h2 class="text-3xl font-semibold">{{ __('landing.features_title') }}</h2>
          <p class="mt-3 text-slate-300 max-w-2xl">{{ __('landing.features_desc') }}</p>
        </div>
        <a href="#download" class="inline-flex px-4 py-2 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 text-sm">
          Get the app →
        </a>
      </div>

      <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <div class="w-10 h-10 rounded-2xl bg-[#7B61FF]/20 border border-[#7B61FF]/30"></div>
          <h3 class="mt-4 font-semibold">{{ __('landing.feat_groups') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.feat_groups_desc') }}</p>
        </div>

        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <div class="w-10 h-10 rounded-2xl bg-green-500/15 border border-green-500/20"></div>
          <h3 class="mt-4 font-semibold">{{ __('landing.feat_att') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.feat_att_desc') }}</p>
        </div>

        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <div class="w-10 h-10 rounded-2xl bg-amber-400/15 border border-amber-400/20"></div>
          <h3 class="mt-4 font-semibold">{{ __('landing.feat_mat') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.feat_mat_desc') }}</p>
        </div>

        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/10"></div>
          <h3 class="mt-4 font-semibold">{{ __('landing.feat_rate') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.feat_rate_desc') }}</p>
        </div>
      </div>

      <!-- Extra: future payments box -->
      <div class="mt-6 p-6 rounded-3xl border border-white/10 bg-gradient-to-r from-white/5 to-[#7B61FF]/10">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <div class="text-sm text-slate-200 font-semibold">Payments (Future)</div>
            <div class="text-sm text-slate-300 mt-1">
              Ready UX for subscriptions, group fees, receipts & payment history when you decide to enable it.
            </div>
          </div>
          <span class="text-xs px-3 py-2 rounded-full border border-white/10 bg-white/5 text-slate-200">
            Coming soon
          </span>
        </div>
      </div>
    </div>
  </section>

  <!-- How it works -->
  <section id="how" class="bg-slate-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <h2 class="text-3xl font-semibold">{{ __('landing.nav_how') }}</h2>

      <div class="mt-10 grid lg:grid-cols-2 gap-4">
        <div class="p-7 rounded-3xl border border-white/10 bg-white/5">
          <div class="text-sm text-[#CFC6FF]"> {{ __('landing.how_students_title') }} </div>
          <ol class="mt-4 space-y-3 text-slate-300">
            <li><span class="text-white font-semibold">1.</span> {{ __('landing.how_s_1') }}</li>
            <li><span class="text-white font-semibold">2.</span> {{ __('landing.how_s_2') }}</li>
            <li><span class="text-white font-semibold">3.</span> {{ __('landing.how_s_3') }}</li>
            <li><span class="text-white font-semibold">4.</span> {{ __('landing.how_s_4') }}</li>
          </ol>
        </div>

        <div class="p-7 rounded-3xl border border-white/10 bg-white/5">
          <div class="text-sm text-[#CFC6FF]"> {{ __('landing.how_teachers_title') }} </div>
          <ol class="mt-4 space-y-3 text-slate-300">
            <li><span class="text-white font-semibold">1.</span> {{ __('landing.how_t_1') }}</li>
            <li><span class="text-white font-semibold">2.</span> {{ __('landing.how_t_2') }}</li>
            <li><span class="text-white font-semibold">3.</span> {{ __('landing.how_t_3') }}</li>
            <li><span class="text-white font-semibold">4.</span> {{ __('landing.how_t_4') }}</li>
          </ol>
        </div>
      </div>
    </div>
  </section>

  <!-- Download / CTA -->
  <section id="download" class="bg-slate-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <div class="relative overflow-hidden rounded-[32px] border border-white/10 bg-gradient-to-br from-[#7B61FF]/20 to-white/5 p-8">
        <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>
        <div class="relative">
          <h2 class="text-3xl font-semibold">{{ __('landing.download_title') }}</h2>
          <p class="mt-3 text-slate-300 max-w-2xl">{{ __('landing.download_desc') }}</p>

          <div class="mt-6 flex flex-col sm:flex-row gap-3">
            <a href="#" class="px-5 py-3 rounded-2xl  hover:opacity-95 text-white font-medium ">
             <img
      src="{{ asset('assets/google_play.png') }}"
      alt="Get it on Google Play"
      class="h-12 w-auto hover:opacity-90 transition"
    >
            </a>
            <a href="#" class="px-5 py-3 rounded-2xl hover:opacity-95 font-medium text-center">
<img
      src="{{ asset('assets/app_store.png') }}"
      alt="Get it on Google Play"
      class="h-12 w-auto hover:opacity-90 transition"
    >            </a>
          </div>

          <p class="mt-4 text-xs text-slate-300">{{ __('landing.payments_note') }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section id="faq" class="bg-slate-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-16">
      <h2 class="text-3xl font-semibold">{{ __('landing.nav_faq') }}</h2>

      <div class="mt-10 grid md:grid-cols-2 gap-4">
        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <h3 class="font-semibold">{{ __('landing.faq_q1') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.faq_a1') }}</p>
        </div>
        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <h3 class="font-semibold">{{ __('landing.faq_q2') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.faq_a2') }}</p>
        </div>
        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <h3 class="font-semibold">{{ __('landing.faq_q3') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.faq_a3') }}</p>
        </div>
        <div class="p-6 rounded-3xl border border-white/10 bg-white/5">
          <h3 class="font-semibold">{{ __('landing.faq_q4') }}</h3>
          <p class="mt-2 text-sm text-slate-300">{{ __('landing.faq_a4') }}</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-slate-950 border-t border-white/10">
    <div class="max-w-6xl mx-auto px-4 py-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
      <div class="text-sm text-slate-300">
        <div class="font-semibold text-white">{{ __('landing.app_name') }}</div>
        <div class="mt-1">© {{ date('Y') }} {{ __('landing.rights') }}</div>
      </div>

      <div class="flex items-center gap-5 text-sm text-slate-300">
        <a href="/about" class="hover:text-white">About</a>
        <a href="/privacy" class="hover:text-white">Privacy</a>
        <a href="/terms" class="hover:text-white">Terms</a>
      </div>
    </div>
  </footer>

</body>
</html>
