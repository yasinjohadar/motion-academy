@extends('frontend.layouts.master')

@section('body_class', 'nl-page')

@php
    /** @var \App\Models\NewsletterSubscriber $subscriber */
    $content = match ($state) {
        'unsubscribed' => [
            'icon' => 'fas fa-check',
            'title' => 'تم إلغاء اشتراكك',
            'text' => 'لن تصلك رسائل القائمة البريدية بعد الآن. نأسف لرحيلك، ويمكنك العودة في أي وقت.',
        ],
        'resubscribed' => [
            'icon' => 'fas fa-heart',
            'title' => 'أهلاً بعودتك!',
            'text' => 'تم تفعيل اشتراكك من جديد، وسيصلك جديد الدورات والمقالات.',
        ],
        default => $subscriber->isSubscribed()
            ? [
                'icon' => 'fas fa-envelope-open-text',
                'title' => 'إلغاء الاشتراك في القائمة البريدية',
                'text' => 'هل تريد التوقف عن استلام رسائلنا؟ يمكنك دائماً الاشتراك من جديد.',
            ]
            : [
                'icon' => 'fas fa-bell-slash',
                'title' => 'اشتراكك ملغى بالفعل',
                'text' => 'هذا البريد غير مشترك حالياً في القائمة البريدية.',
            ],
    };
@endphp

@section('content')
    <main class="container nl-wrap">
        <div class="nl-card">
            <span class="nl-icon nl-icon--{{ $state }}"><i class="{{ $content['icon'] }}"></i></span>
            <h1 class="nl-title">{{ $content['title'] }}</h1>
            <p class="nl-text">{{ $content['text'] }}</p>
            <p class="nl-email en-text" dir="ltr">{{ $subscriber->email }}</p>

            <div class="nl-actions">
                @if ($state === 'confirm' && $subscriber->isSubscribed())
                    <form method="post" action="{{ route('newsletter.unsubscribe.confirm', $subscriber->token) }}">
                        @csrf
                        <button type="submit" class="nl-btn nl-btn--danger"><i class="fas fa-bell-slash"></i> نعم، ألغِ اشتراكي</button>
                    </form>
                    <a href="{{ route('home') }}" class="nl-btn nl-btn--ghost">لا، أريد البقاء</a>
                @elseif (! $subscriber->isSubscribed())
                    <form method="post" action="{{ route('newsletter.resubscribe', $subscriber->token) }}">
                        @csrf
                        <button type="submit" class="nl-btn nl-btn--primary"><i class="fas fa-bell"></i> أعد اشتراكي</button>
                    </form>
                    <a href="{{ route('home') }}" class="nl-btn nl-btn--ghost">العودة للموقع</a>
                @else
                    <a href="{{ route('courses') }}" class="nl-btn nl-btn--primary"><i class="fas fa-play-circle"></i> تصفّح الدورات</a>
                    <a href="{{ route('home') }}" class="nl-btn nl-btn--ghost">العودة للموقع</a>
                @endif
            </div>
        </div>
    </main>
@endsection

@push('styles')
<style>
    .nl-wrap { min-height: 60vh; display: grid; place-items: center; padding-block: 56px; }
    .nl-card {
        width: min(520px, 100%); padding: 40px 32px; text-align: center; border-radius: 26px;
        background: var(--card-bg, #fff); border: 1px solid rgba(51, 164, 218, 0.18);
        box-shadow: 0 30px 70px rgba(15, 42, 74, 0.1);
    }
    html[data-theme="dark"] .nl-card { background: rgba(11, 33, 64, 0.85); border-color: rgba(69, 190, 196, 0.2); }
    .nl-icon {
        width: 78px; height: 78px; margin: 0 auto 18px; display: grid; place-items: center; border-radius: 50%;
        font-size: 1.9rem; color: #fff; background: linear-gradient(135deg, #02ad8f, #45bec4 45%, #33a4da 75%, #1a83c1);
        box-shadow: 0 16px 36px rgba(26, 131, 193, 0.3);
    }
    .nl-title { font-family: 'Alexandria', sans-serif; font-weight: 800; font-size: 1.6rem; color: var(--text-primary); margin-bottom: 10px; }
    .nl-text { color: var(--text-secondary); line-height: 1.9; margin-bottom: 8px; }
    .nl-email { display: inline-block; padding: 4px 14px; border-radius: 999px; font-size: 0.85rem; font-weight: 600; color: #1a83c1; background: rgba(51, 164, 218, 0.1); }
    .nl-actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 24px; }
    .nl-actions form { margin: 0; }
    .nl-btn {
        display: inline-flex; align-items: center; gap: 8px; height: 48px; padding: 0 22px; border-radius: 14px;
        font-weight: 800; text-decoration: none; border: 0; cursor: pointer;
    }
    .nl-btn--primary { color: #fff; background: linear-gradient(135deg, #02ad8f, #45bec4 45%, #33a4da 75%, #1a83c1); }
    .nl-btn--danger { color: #fff; background: #0f2a4a; }
    html[data-theme="dark"] .nl-btn--danger { background: #1a83c1; }
    .nl-btn--ghost { color: var(--text-primary); border: 1.5px solid rgba(51, 164, 218, 0.35); background: transparent; }
</style>
@endpush
