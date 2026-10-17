{{--
    The enrollment call-to-action on a course page, one state at a time:
    guest · can request · pending · approved · rejected (may ask again) · course full.

    @var \App\Models\Course $course
    @var \App\Models\CourseEnrollment|null $enrollment
    @var bool $isFull
--}}
<div class="enr-box" id="enroll">
    @if (session('enrollment_success'))
        <div class="enr-flash" role="status"><i class="fas fa-check-circle"></i> {{ session('enrollment_success') }}</div>
    @endif
    @error('enrollment')
        <div class="enr-flash is-error" role="alert"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>
    @enderror

    @guest
        <a href="{{ route('courses.enroll.start', $course->slug) }}" class="cd-btn-primary">
            <i class="fas fa-sign-in-alt"></i> سجّل دخولك لطلب الانضمام
        </a>
        <a href="{{ route('register') }}" class="cd-side-link">ليس لديك حساب؟ أنشئ حسابك مجاناً <i class="fas fa-arrow-left"></i></a>
    @else
        @if ($enrollment?->isApproved())
            <div class="enr-state is-approved">
                <span class="enr-state-icon"><i class="fas fa-check"></i></span>
                <div>
                    <strong>أنت منضم إلى هذه الدورة</strong>
                    <small>منذ {{ $enrollment->decided_at?->translatedFormat('d M Y') ?? $enrollment->updated_at?->translatedFormat('d M Y') }}</small>
                </div>
            </div>
            <div class="enr-progress">
                <div class="sd-bar"><span style="width: {{ $enrollment->progress_percent }}%"></span></div>
                <span class="en-text">{{ $enrollment->progress_percent }}%</span>
            </div>
            <a href="{{ route('learn.course', $course->slug) }}" class="cd-btn-primary">
                <i class="fas fa-play"></i>
                {{ $enrollment->isCompleted() ? 'راجع الدورة' : ($enrollment->progress_percent > 0 ? 'تابع التعلّم' : 'ابدأ التعلّم الآن') }}
            </a>
            <a href="{{ route('student.dashboard') }}" class="cd-side-link">لوحتي <i class="fas fa-arrow-left"></i></a>
        @elseif ($enrollment?->isPending())
            <div class="enr-state is-pending">
                <span class="enr-state-icon"><i class="fas fa-hourglass-half"></i></span>
                <div>
                    <strong>طلبك قيد المراجعة</strong>
                    <small>أُرسل {{ $enrollment->created_at?->diffForHumans() }} — سنبلغك بالنتيجة عبر بريدك.</small>
                </div>
            </div>
            <form method="post" action="{{ route('courses.enroll.cancel', $course->slug) }}" onsubmit="return confirm('هل تريد سحب طلب الانضمام؟')">
                @csrf
                @method('delete')
                <button type="submit" class="enr-cancel"><i class="fas fa-times"></i> سحب الطلب</button>
            </form>
        @elseif ($isFull)
            <div class="enr-state is-full">
                <span class="enr-state-icon"><i class="fas fa-user-lock"></i></span>
                <div>
                    <strong>اكتمل عدد المقاعد</strong>
                    <small>لا تتوفر أماكن جديدة في هذه الدورة حالياً.</small>
                </div>
            </div>
        @else
            @if ($enrollment?->isRejected())
                <div class="enr-state is-rejected">
                    <span class="enr-state-icon"><i class="fas fa-info"></i></span>
                    <div>
                        <strong>لم تتم الموافقة على طلبك السابق</strong>
                        @if ($enrollment->admin_note)
                            <small>{{ $enrollment->admin_note }}</small>
                        @endif
                    </div>
                </div>
            @endif
            <form method="post" action="{{ route('courses.enroll', $course->slug) }}" class="enr-form">
                @csrf
                <details class="enr-note" @error('message') open @enderror>
                    <summary><i class="fas fa-comment-dots"></i> إضافة رسالة للإدارة (اختياري)</summary>
                    <textarea name="message" rows="3" maxlength="1000" placeholder="مثال: أعمل مصمماً وأريد تعلّم التحريك لعملي...">{{ old('message') }}</textarea>
                    @error('message')
                        <small class="enr-error">{{ $message }}</small>
                    @enderror
                </details>
                <button type="submit" class="cd-btn-primary">
                    <i class="fas fa-paper-plane"></i> {{ $enrollment?->isRejected() ? 'أعد طلب الانضمام' : 'اطلب الانضمام للدورة' }}
                </button>
            </form>
            <p class="enr-hint"><i class="fas fa-shield-alt"></i> تتم مراجعة الطلبات من الإدارة قبل التفعيل.</p>
        @endif
    @endguest
</div>
