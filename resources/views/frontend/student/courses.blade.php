@extends('frontend.layouts.student')

@section('sd_title', 'دوراتي')
@section('sd_subtitle', 'كل الدورات التي انضممت إليها، مع تقدّمك في كل منها.')

@section('student')
    <nav class="sd-tabs" aria-label="تصفية الدورات">
        <a href="{{ route('student.courses') }}" class="sd-tab{{ $tab === 'active' ? ' is-active' : '' }}">
            <i class="ri-play-circle-line"></i> قيد التعلّم <span class="en-text">{{ $counts['active'] }}</span>
        </a>
        <a href="{{ route('student.courses', ['tab' => 'completed']) }}" class="sd-tab{{ $tab === 'completed' ? ' is-active' : '' }}">
            <i class="ri-trophy-line"></i> مكتملة <span class="en-text">{{ $counts['completed'] }}</span>
        </a>
    </nav>

    @if ($enrollments->isEmpty())
        <div class="sd-panel">
            <div class="sd-empty">
                @if ($tab === 'completed')
                    <i class="ri-trophy-line"></i>
                    <strong>لم تُكمل أي دورة بعد</strong>
                    <p>عندما تنهي كل دروس دورة ستظهر هنا. استمر — أنت أقرب مما تظن!</p>
                    <a href="{{ route('student.courses') }}" class="sd-btn"><i class="fas fa-play"></i> دوراتي الحالية</a>
                @else
                    <i class="ri-book-open-line"></i>
                    <strong>لا توجد دورات قيد التعلّم</strong>
                    <p>اطلب الانضمام إلى دورة، وستظهر هنا فور موافقة الإدارة.</p>
                    <a href="{{ route('courses') }}" class="sd-btn"><i class="fas fa-search"></i> تصفّح الدورات</a>
                @endif
            </div>
        </div>
    @else
        <div class="sd-course-grid">
            @foreach ($enrollments as $enrollment)
                <x-frontend.learning-card :enrollment="$enrollment" :done="$doneCounts[$enrollment->course_id] ?? 0" />
            @endforeach
        </div>
    @endif
@endsection
