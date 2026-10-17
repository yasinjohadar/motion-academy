@extends('frontend.layouts.student')

@section('sd_title', 'الطلبات والاشتراكات')
@section('sd_subtitle', 'كل طلبات الانضمام التي أرسلتها، وحالة كل اشتراك.')

@php
    $statusMeta = [
        'pending' => ['icon' => 'ri-hourglass-line', 'label' => 'بانتظار الموافقة'],
        'approved' => ['icon' => 'ri-checkbox-circle-line', 'label' => 'مشترك'],
        'rejected' => ['icon' => 'ri-close-circle-line', 'label' => 'مرفوض'],
    ];
@endphp

@section('student')
    @if ($enrollments->isEmpty())
        <div class="sd-panel">
            <div class="sd-empty">
                <i class="ri-file-list-3-line"></i>
                <strong>لا توجد طلبات بعد</strong>
                <p>من صفحة أي دورة اضغط «اطلب الانضمام»، وستتابع طلبك من هنا.</p>
                <a href="{{ route('courses') }}" class="sd-btn"><i class="fas fa-search"></i> تصفّح الدورات</a>
            </div>
        </div>
    @else
        <div class="sd-panel sd-requests">
            @foreach ($enrollments as $enrollment)
                @php($meta = $statusMeta[$enrollment->status] ?? $statusMeta['pending'])
                <article class="sd-request is-{{ $enrollment->status }}">
                    <span class="sd-status is-{{ $enrollment->status }}"><i class="{{ $meta['icon'] }}"></i></span>

                    <div class="sd-request-body">
                        <a href="{{ route('courses.show', $enrollment->course->slug) }}" class="sd-request-title">{{ $enrollment->course->title }}</a>
                        <div class="sd-request-meta">
                            <span class="sd-pill is-{{ $enrollment->status }}">{{ $meta['label'] }}</span>
                            <span><i class="far fa-calendar"></i> أُرسل {{ $enrollment->created_at?->translatedFormat('d M Y') }}</span>
                            @if ($enrollment->decided_at)
                                <span><i class="far fa-calendar-check"></i> {{ $enrollment->isApproved() ? 'فُعّل' : 'رُدّ' }} {{ $enrollment->decided_at->translatedFormat('d M Y') }}</span>
                            @endif
                            @if ($enrollment->source === \App\Models\CourseEnrollment::SOURCE_ADMIN)
                                <span><i class="ri-shield-user-line"></i> أضافتك الإدارة</span>
                            @endif
                        </div>
                        @if ($enrollment->isRejected() && $enrollment->admin_note)
                            <p class="sd-request-note"><i class="ri-chat-quote-line"></i> {{ $enrollment->admin_note }}</p>
                        @endif
                    </div>

                    <div class="sd-request-action">
                        @if ($enrollment->isApproved())
                            <a href="{{ route('learn.course', $enrollment->course->slug) }}" class="sd-btn is-small"><i class="fas fa-play"></i> ادخل الدورة</a>
                        @elseif ($enrollment->isPending())
                            <form method="post" action="{{ route('courses.enroll.cancel', $enrollment->course->slug) }}" onsubmit="return confirm('هل تريد سحب طلب الانضمام؟')">
                                @csrf
                                @method('delete')
                                <button type="submit" class="sd-btn sd-btn-ghost is-small"><i class="fas fa-times"></i> سحب الطلب</button>
                            </form>
                        @else
                            <a href="{{ route('courses.show', $enrollment->course->slug) }}#enroll" class="sd-btn sd-btn-ghost is-small"><i class="fas fa-redo"></i> أعد الطلب</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
