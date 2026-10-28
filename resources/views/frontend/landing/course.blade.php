{{--
    Course landing page for ads — deliberately standalone: no site layout, no
    Bootstrap, no icon font, one inline stylesheet and ~20 lines of JS.
    Blocks render top to bottom and any empty block is skipped.
    Shared details (title, cover, price, stats) come from the course.
--}}
@php
    $videos = $landing->youtubeVideos();
    $goals = array_values(array_filter($landing->goals ?? [], 'filled'));
    $features = $landing->features ?? [];
    $testimonials = $landing->testimonialCards();
    $faqs = $landing->faqs ?? [];
    $whatsapp = $landing->whatsappUrl();
    // The promoted WhatsApp link is not repeated among the small buttons.
    $socialButtons = array_values(array_filter(
        $landing->socialButtons(),
        fn (array $button) => $button['url'] !== $whatsapp,
    ));
    $faqSchema = $landing->faqSchema();
    $cover = $course->thumbnailUrl();
    $tagline = $landing->tagline ?: $course->subtitle;
    $showPrice = $landing->show_price;
    $originalPrice = $course->formattedOriginalPrice();
    // [value, label] boxes under the title, like the site's page headers.
    $stats = array_values(array_filter([
        $course->lessons_count ? [$course->lessons_count, 'درس'] : null,
        $course->duration_seconds ? [$course->formattedDuration(), 'المدة'] : null,
        $course->level ? [$course->level->label(), 'المستوى'] : null,
        $course->has_certificate ? ['✓', 'شهادة إتمام'] : null,
    ]));
    $check = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>';
    $play = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#02ad8f">
<title>{{ $meta['title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}">
<meta name="robots" content="{{ $meta['indexable'] ? 'index, follow' : 'noindex, nofollow' }}">
<link rel="canonical" href="{{ $meta['url'] }}">
<meta property="og:type" content="website">
<meta property="og:title" content="{{ $meta['title'] }}">
<meta property="og:description" content="{{ $meta['description'] }}">
<meta property="og:url" content="{{ $meta['url'] }}">
@if ($meta['image'])
<meta property="og:image" content="{{ $meta['image'] }}">
<meta name="twitter:card" content="summary_large_image">
@endif
<link rel="icon" type="image/png" href="{{ $fa }}/images/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@400;600;800&display=swap" rel="stylesheet">
@if ($faqSchema)
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endif
@if ($videos || collect($testimonials)->whereNotNull('video_id')->isNotEmpty())
<link rel="preconnect" href="https://i.ytimg.com">
@endif
<style>
:root{--g1:#02ad8f;--g2:#1a83c1;--grad:linear-gradient(135deg,#02ad8f,#22b3b6 45%,#33a4da 75%,#1a83c1);--ink:#0f2a4a;--muted:#5b6b7f;--bg:#f5f9fc;--card:#fff;--line:#e3edf3;--r:18px}
*{box-sizing:border-box;margin:0}
html{-webkit-text-size-adjust:100%;scroll-behavior:smooth}
body{font-family:Alexandria,Tahoma,sans-serif;background:var(--bg);color:var(--ink);line-height:1.8;font-size:16px;padding-bottom:84px}
img,iframe,video{max-width:100%}
a{color:inherit}
.w{width:100%;max-width:760px;margin:0 auto;padding:0 16px}
.blk{padding:40px 0}
.blk:nth-of-type(even){background:#fff}
.h2{font-size:clamp(1.3rem,4.6vw,1.75rem);font-weight:800;margin-bottom:20px;line-height:1.4}
.h2::after{content:"";display:block;width:48px;height:4px;border-radius:4px;background:var(--grad);margin-top:10px}
.preview{background:#fff4d6;color:#7a5600;text-align:center;font-size:.85rem;padding:8px 16px}
.hero{position:relative;overflow:hidden;color:#fff;text-align:center;padding:26px 0 40px;border-bottom:2px solid rgba(255,255,255,.15);background:radial-gradient(30rem 16rem at 90% 10%,rgba(255,255,255,.1),transparent 60%),radial-gradient(26rem 14rem at 5% 95%,rgba(6,20,39,.22),transparent 60%),linear-gradient(135deg,#018a72 0%,#1b8f9a 45%,#1a6fa6 75%,#123b6e 100%)}
.hero::before,.hero::after{content:"";position:absolute;pointer-events:none;clip-path:polygon(15% 0,100% 50%,15% 100%);background:linear-gradient(135deg,rgba(255,255,255,.5),rgba(255,255,255,.12));animation:float 7s ease-in-out infinite}
.hero::before{width:clamp(56px,9vw,90px);aspect-ratio:1;top:14%;inset-inline-start:5%}
.hero::after{width:clamp(40px,6vw,58px);aspect-ratio:1;bottom:12%;inset-inline-end:6%;animation-delay:-3.5s}
@keyframes float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-12px) rotate(-6deg)}}
.hero .w{position:relative;z-index:1}
.logo{display:block;height:36px;width:auto;margin:0 auto 22px}
.brand{display:inline-block;font-weight:800;margin-bottom:22px;font-size:1.05rem}
.cat{display:inline-flex;align-items:center;gap:7px;padding:6px 16px;border-radius:50px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.35);font-size:.8rem;font-weight:700;margin-bottom:14px}
.cat svg{width:14px;height:14px;fill:#fff}
h1{font-size:clamp(1.65rem,6.2vw,2.6rem);line-height:1.35;font-weight:800;margin-bottom:12px}
h1::after{content:"";display:block;width:82px;height:10px;margin:14px auto 0;opacity:.85;background:linear-gradient(90deg,#fff,#fff) left center/26px 3px no-repeat,linear-gradient(90deg,#fff,#fff) right center/26px 3px no-repeat,conic-gradient(from 60deg at 0 50%,#fff 0 60deg,transparent 0) 30px 0/8px 10px no-repeat,conic-gradient(from 60deg at 0 50%,#fff 0 60deg,transparent 0) 38px 0/8px 10px no-repeat,conic-gradient(from 60deg at 0 50%,#fff 0 60deg,transparent 0) 46px 0/8px 10px no-repeat}
.tag{max-width:600px;margin:0 auto 20px;color:rgba(255,255,255,.9);font-size:clamp(.98rem,3.6vw,1.1rem)}
.stats{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:0 auto 18px;padding:12px;border-radius:16px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);max-width:640px}
.stat{display:flex;flex-direction:column;align-items:center;gap:2px;flex:1 1 92px;max-width:150px;padding:10px 12px;border-radius:14px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28)}
.stat strong{font-size:1.12rem;font-weight:800;line-height:1.4}
.stat span{font-size:.76rem;color:rgba(255,255,255,.78)}
.by{font-size:.92rem;color:rgba(255,255,255,.88);margin-bottom:20px}
.buy{display:flex;flex-direction:column;align-items:center;gap:14px;max-width:520px;margin:0 auto}
.price{font-size:1.9rem;font-weight:800;line-height:1.2}
.price del{display:block;font-size:.95rem;font-weight:400;opacity:.7}
.off{font-size:.75rem;font-weight:800;padding:3px 10px;border-radius:99px;background:#ffcc4d;color:#5a3d00;vertical-align:middle;margin-inline-start:6px}
.cta{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:54px;padding:12px 28px;border-radius:14px;background:var(--grad);color:#fff;font-weight:800;font-size:1.05rem;text-decoration:none;box-shadow:0 12px 28px rgba(2,173,143,.35);transition:transform .2s}
.cta:active{transform:scale(.98)}
.buy .cta{width:100%;background:#fff;color:#0b6e7a;box-shadow:0 14px 30px rgba(6,20,39,.25)}
.cover{display:block;width:100%;max-width:640px;margin:28px auto 0;aspect-ratio:16/9;object-fit:cover;border-radius:var(--r);background:rgba(255,255,255,.12);border:4px solid rgba(255,255,255,.22);box-shadow:0 24px 50px rgba(6,20,39,.35)}
.prose{font-size:1rem;overflow-wrap:anywhere}
.prose>*+*{margin-top:14px}
.prose h2,.prose h3{line-height:1.5}
.prose img{height:auto;border-radius:12px}
.prose ul,.prose ol{padding-inline-start:22px}
.prose table{display:block;overflow-x:auto;border-collapse:collapse}
.prose td,.prose th{border:1px solid var(--line);padding:6px 10px}
.prose iframe{width:100%;aspect-ratio:16/9;height:auto;border:0;border-radius:12px}
.vids{display:grid;gap:18px}
.yt{position:relative;display:block;width:100%;aspect-ratio:16/9;border:0;padding:0;border-radius:var(--r);overflow:hidden;background:#000;cursor:pointer}
.yt img{width:100%;height:100%;object-fit:cover;opacity:.9}
.yt span{position:absolute;inset:0;margin:auto;width:68px;height:48px;border-radius:14px;background:#f00;display:grid;place-items:center;box-shadow:0 6px 20px rgba(0,0,0,.35)}
.yt svg{width:28px;fill:#fff}
.yt iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.vt{font-weight:600;margin-top:8px;font-size:.95rem}
.list{list-style:none;padding:0;display:grid;gap:12px}
.list li{display:flex;gap:12px;align-items:flex-start;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px 16px}
.list svg{flex:0 0 26px;width:26px;height:26px;padding:4px;border-radius:50%;background:var(--grad);fill:#fff;margin-top:2px}
.feats{display:grid;gap:14px}
.feat{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px 18px 18px 20px;border-inline-start:4px solid var(--g1)}
.feat b{display:block;font-size:1.05rem;margin-bottom:4px}
.feat p{color:var(--muted);font-size:.95rem}
.tms{display:grid;gap:16px}
.tm{background:var(--card);border:1px solid var(--line);border-radius:var(--r);padding:18px}
.tm q{display:block;font-size:.98rem;margin-bottom:12px;quotes:"«" "»"}
.tm .yt{margin-bottom:12px}
.who b{display:block}
.who small{color:var(--muted)}
.final{background:var(--grad)!important;color:#fff;text-align:center}
.final .h2::after{margin-inline:auto;background:#fff}
.final p{margin-bottom:20px;opacity:.95}
.final .cta{background:#fff;color:var(--g2);width:100%;max-width:420px}
.soc{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;padding:0;list-style:none}
.soc a{display:inline-flex;align-items:center;min-height:44px;padding:8px 18px;border-radius:99px;color:#fff;font-weight:600;font-size:.9rem;text-decoration:none}
.faq{display:grid;gap:12px}
.faq details{background:var(--card);border:1px solid var(--line);border-radius:14px;overflow:hidden;transition:border-color .2s,box-shadow .2s}
.faq details[open]{border-color:var(--g1);box-shadow:0 10px 26px rgba(2,173,143,.12)}
.faq summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;font-weight:600;min-height:56px}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"";flex:0 0 28px;height:28px;border-radius:50%;background:#eaf6f3 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%2302ad8f' d='M11 5h2v6h6v2h-6v6h-2v-6H5v-2h6z'/%3E%3C/svg%3E") center/18px no-repeat;transition:transform .25s}
.faq details[open] summary::after{transform:rotate(45deg)}
.faq .ans{padding:0 18px 18px;color:var(--muted);white-space:pre-line}
.wa{position:relative;display:flex;align-items:center;gap:14px;width:100%;max-width:520px;margin:0 auto 18px;padding:18px 22px;border-radius:20px;background:linear-gradient(135deg,#25d366,#128c7e);color:#fff;text-decoration:none;box-shadow:0 14px 32px rgba(18,140,126,.4);animation:wa-pulse 2s ease-out infinite}
.wa svg{flex:0 0 52px;width:52px;height:52px;animation:wa-shake 3.2s ease-in-out infinite}
.wa b{display:block;font-size:clamp(1.1rem,4.4vw,1.35rem);font-weight:800;line-height:1.35}
.wa small{display:block;opacity:.92;font-size:.9rem}
.wa i{margin-inline-start:auto;font-style:normal;font-size:1.6rem;line-height:1}
.wa:active{transform:scale(.98)}
.wa-hero{max-width:520px;margin:14px auto 0;text-align:start;border:2px solid rgba(255,255,255,.55)}
@keyframes wa-pulse{0%{box-shadow:0 14px 32px rgba(18,140,126,.4),0 0 0 0 rgba(37,211,102,.55)}70%{box-shadow:0 14px 32px rgba(18,140,126,.4),0 0 0 18px rgba(37,211,102,0)}100%{box-shadow:0 14px 32px rgba(18,140,126,.4),0 0 0 0 rgba(37,211,102,0)}}
@keyframes wa-shake{0%,70%,100%{transform:rotate(0)}74%{transform:rotate(-14deg) scale(1.08)}78%{transform:rotate(12deg) scale(1.08)}82%{transform:rotate(-10deg)}86%{transform:rotate(8deg)}90%{transform:rotate(0)}}
.foot{text-align:center;color:var(--muted);font-size:.8rem;padding:22px 16px}
.bar{position:fixed;inset:auto 0 0 0;z-index:9;display:flex;align-items:center;gap:12px;padding:10px 16px calc(10px + env(safe-area-inset-bottom));background:rgba(255,255,255,.96);border-top:1px solid var(--line);box-shadow:0 -8px 24px rgba(15,42,74,.08);transform:translateY(110%);transition:transform .3s}
.bar.on{transform:none}
.wa-float{position:fixed;z-index:10;inset-inline-end:16px;max-width:calc(100% - 32px);line-height:1.4;bottom:calc(16px + env(safe-area-inset-bottom));display:inline-flex;align-items:center;gap:10px;padding:10px 20px 10px 12px;border-radius:99px;background:linear-gradient(135deg,#25d366,#128c7e);color:#fff;font-weight:800;font-size:1rem;text-decoration:none;box-shadow:0 12px 28px rgba(18,140,126,.45);animation:wa-pulse 2s ease-out infinite}
.wa-float svg{width:40px;height:40px;flex:0 0 40px;animation:wa-shake 3.2s ease-in-out infinite}
.wa-float:active{transform:scale(.97)}
.h2.c{text-align:center}
.h2.c::after{margin-inline:auto}
.bar .cta{flex:1;min-height:48px;font-size:1rem;padding:10px 16px}
.bar .price{font-size:1.15rem;color:var(--ink)}
@media (min-width:720px){body{padding-bottom:0}.bar{display:none}.vids,.feats,.tms{grid-template-columns:1fr 1fr}.vids:has(>:only-child),.tms:has(>:only-child){grid-template-columns:1fr}.blk{padding:56px 0}}
@media (prefers-reduced-motion:reduce){html{scroll-behavior:auto}*{transition:none!important;animation:none!important}}
</style>
</head>
<body>
@if ($isPreview)
    <div class="preview">معاينة — {{ $landing->is_published ? 'الصفحة منشورة' : 'الصفحة غير منشورة ولا يراها الزوار' }}</div>
@endif

<header class="hero">
    <div class="w">
        @if ($siteSettings['logo_url'] ?? null)
            <img src="{{ $siteSettings['logo_url'] }}" alt="{{ $siteSettings['site_name'] }}" class="logo" height="36">
        @else
            <span class="brand">{{ $siteSettings['site_name'] ?? config('app.name') }}</span>
        @endif

        @if ($course->category)
            <div><span class="cat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3h8v8H3zm10 0h8v8h-8zM3 13h8v8H3zm10 0h8v8h-8z"/></svg>{{ $course->category->name }}</span></div>
        @endif
        <h1>{{ $course->title }}</h1>
        @if ($tagline)
            <p class="tag">{{ $tagline }}</p>
        @endif
        @if ($stats)
            <div class="stats">
                @foreach ($stats as [$value, $label])
                    <div class="stat"><strong>{{ $value }}</strong><span>{{ $label }}</span></div>
                @endforeach
            </div>
        @endif
        @if ($course->instructor)
            <p class="by">المدرّب: <strong>{{ $course->instructor->name }}</strong></p>
        @endif

        <div class="buy" id="buy">
            @if ($showPrice)
                <div class="price">
                    @if ($originalPrice)<del>{{ $originalPrice }}</del>@endif
                    {{ $course->formattedPrice() }}
                    @if ($percent = $course->discountPercent())<span class="off">خصم {{ $percent }}%</span>@endif
                </div>
            @endif
            <a href="{{ $landing->ctaUrl() }}" class="cta">{{ $landing->ctaLabel() }}</a>
        </div>
        @if ($whatsapp)
            @include('frontend.landing.partials.whatsapp-button', ['class' => 'wa-hero'])
        @endif

        @if ($cover)
            <img src="{{ $cover }}" alt="{{ $course->title }}" class="cover" width="1280" height="720" decoding="async">
        @endif
    </div>
</header>

<main>
    @if ($landing->hasDescription())
        <section class="blk">
            <div class="w">
                <h2 class="h2">عن الدورة</h2>
                <div class="prose">{!! $landing->description !!}</div>
            </div>
        </section>
    @endif

    @if ($videos)
        <section class="blk">
            <div class="w">
                <h2 class="h2">شاهد من الدورة</h2>
                <div class="vids">
                    @foreach ($videos as $video)
                        <div>
                            <button type="button" class="yt" data-yt="{{ $video['id'] }}" aria-label="تشغيل {{ $video['title'] ?: 'الفيديو' }}">
                                <img src="https://i.ytimg.com/vi/{{ $video['id'] }}/hqdefault.jpg" alt="" loading="lazy" decoding="async" width="480" height="360">
                                <span>{!! $play !!}</span>
                            </button>
                            @if ($video['title'])<p class="vt">{{ $video['title'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($goals)
        <section class="blk">
            <div class="w">
                <h2 class="h2">ماذا ستتعلم</h2>
                <ul class="list">
                    @foreach ($goals as $goal)<li>{!! $check !!}<span>{{ $goal }}</span></li>@endforeach
                </ul>
            </div>
        </section>
    @endif

    @if ($features)
        <section class="blk">
            <div class="w">
                <h2 class="h2">مميزات الدورة</h2>
                <div class="feats">
                    @foreach ($features as $feature)
                        <div class="feat">
                            <b>{{ $feature['title'] }}</b>
                            @if (filled($feature['text'] ?? null))<p>{{ $feature['text'] }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($testimonials)
        <section class="blk">
            <div class="w">
                <h2 class="h2">آراء المتدربين</h2>
                <div class="tms">
                    @foreach ($testimonials as $item)
                        <figure class="tm">
                            @if ($item['video_id'])
                                <button type="button" class="yt" data-yt="{{ $item['video_id'] }}" aria-label="تشغيل رأي {{ $item['name'] }}">
                                    <img src="https://i.ytimg.com/vi/{{ $item['video_id'] }}/hqdefault.jpg" alt="" loading="lazy" decoding="async" width="480" height="360">
                                    <span>{!! $play !!}</span>
                                </button>
                            @endif
                            @if ($item['text'])<q>{{ $item['text'] }}</q>@endif
                            <figcaption class="who">
                                <b>{{ $item['name'] }}</b>
                                @if ($item['role'])<small>{{ $item['role'] }}</small>@endif
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($faqs)
        <section class="blk">
            <div class="w">
                <h2 class="h2">الأسئلة الشائعة</h2>
                <div class="faq">
                    @foreach ($faqs as $faq)
                        <details{{ $loop->first ? ' open' : '' }}>
                            <summary>{{ $faq['question'] }}</summary>
                            <div class="ans">{{ $faq['answer'] }}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="blk final">
        <div class="w">
            <h2 class="h2">ابدأ رحلتك اليوم</h2>
            <p>{{ $course->title }}@if ($showPrice) — {{ $course->formattedPrice() }}@endif</p>
            <a href="{{ $landing->ctaUrl() }}" class="cta">{{ $landing->ctaLabel() }}</a>
        </div>
    </section>

    @if ($whatsapp || $socialButtons)
        <section class="blk">
            <div class="w">
                <h2 class="h2 c">تواصل معنا</h2>
                @if ($whatsapp)
                    @include('frontend.landing.partials.whatsapp-button')
                @endif
                @if ($socialButtons)
                    <ul class="soc">
                        @foreach ($socialButtons as $button)
                            <li><a href="{{ $button['url'] }}" style="background:{{ $button['color'] }}" target="_blank" rel="noopener noreferrer">{{ $button['label'] }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    @endif
</main>

<footer class="foot">© {{ now()->year }} {{ $siteSettings['site_name'] ?? config('app.name') }}</footer>

@if ($whatsapp)
    {{-- Always on screen, so the visitor can reach us from anywhere on the page. --}}
    <a href="{{ $whatsapp }}" class="wa-float" target="_blank" rel="noopener noreferrer">
        @include('frontend.landing.partials.whatsapp-icon')
        <span>تواصل معنا للاشتراك بالكورس</span>
    </a>
@else
    <div class="bar" id="bar" aria-hidden="true">
        @if ($showPrice)<span class="price">{{ $course->formattedPrice() }}</span>@endif
        <a href="{{ $landing->ctaUrl() }}" class="cta" tabindex="-1">{{ $landing->ctaLabel() }}</a>
    </div>
@endif

<script>
// Tap-to-load YouTube: only a thumbnail until the visitor asks for the player.
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-yt]');
    if (!b) return;
    var f = document.createElement('iframe');
    f.src = 'https://www.youtube-nocookie.com/embed/' + b.dataset.yt + '?autoplay=1&rel=0&playsinline=1';
    f.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    f.allowFullscreen = true;
    f.title = b.getAttribute('aria-label') || 'YouTube';
    var box = document.createElement('div');
    box.className = 'yt';
    box.appendChild(f);
    b.replaceWith(box);
});
// Sticky mobile button once the hero's own button scrolls away.
(function () {
    var buy = document.getElementById('buy'), bar = document.getElementById('bar');
    if (!buy || !bar || !('IntersectionObserver' in window)) return;
    new IntersectionObserver(function (entries) {
        var on = !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0;
        bar.classList.toggle('on', on);
        bar.setAttribute('aria-hidden', on ? 'false' : 'true');
        bar.querySelector('a').tabIndex = on ? 0 : -1;
    }).observe(buy);
})();
</script>
</body>
</html>
