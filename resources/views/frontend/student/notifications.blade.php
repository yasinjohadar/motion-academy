@extends('frontend.layouts.student')

@section('sd_title', 'الإشعارات')
@section('sd_subtitle', 'قرارات طلباتك وكل ما يخص دوراتك.')

@section('sd_actions')
    @if (($account['unread'] ?? 0) > 0)
        <form method="post" action="{{ route('student.notifications.read-all') }}">
            @csrf
            <button type="submit" class="sd-btn sd-btn-light"><i class="fas fa-check-double"></i> تعليم الكل كمقروء</button>
        </form>
    @endif
@endsection

@section('student')
    <nav class="sd-tabs" aria-label="تصفية الإشعارات">
        <a href="{{ route('student.notifications') }}" class="sd-tab{{ $state === null ? ' is-active' : '' }}"><i class="ri-inbox-line"></i> الكل</a>
        <a href="{{ route('student.notifications', ['state' => 'unread']) }}" class="sd-tab{{ $state === 'unread' ? ' is-active' : '' }}">
            <i class="ri-mail-unread-line"></i> غير المقروءة <span class="en-text">{{ $account['unread'] ?? 0 }}</span>
        </a>
    </nav>

    <section class="sd-panel">
        @forelse ($notifications as $notification)
            @include('frontend.student.partials.notification', ['notification' => $notification])
        @empty
            <div class="sd-empty">
                <i class="ri-notification-off-line"></i>
                <strong>{{ $state === 'unread' ? 'لا إشعارات غير مقروءة' : 'لا إشعارات بعد' }}</strong>
                <p>سنبلغك هنا عند قبول طلباتك أو أي جديد يخص دوراتك.</p>
            </div>
        @endforelse
    </section>

    @if ($notifications->hasPages())
        <div class="sd-pagination">{{ $notifications->links('pagination::bootstrap-5') }}</div>
    @endif
@endsection
