{{--
    One notification row. $notification is AppNotification::toAdminArray().
    Opening it marks it read (POST), then follows its link.
--}}
@php($compact = $compact ?? false)

<div class="sd-note is-{{ $notification['level'] }}{{ $notification['is_read'] ? '' : ' is-unread' }}">
    <span class="sd-note-icon"><i class="{{ $notification['icon'] }}"></i></span>
    <div class="sd-note-body">
        <strong>{{ $notification['title'] }}</strong>
        @unless ($compact)
            <p>{{ $notification['message'] }}</p>
        @endunless
        <small>{{ $notification['created_at_human'] }}@if (! $compact && $notification['category_label']) · {{ $notification['category_label'] }}@endif</small>
    </div>
    @if ($notification['action_url'] || ! $notification['is_read'])
        <form method="post" action="{{ route('student.notifications.read', $notification['id']) }}" class="sd-note-action">
            @csrf
            @if ($notification['action_url'])
                <input type="hidden" name="open" value="1">
                <button type="submit" class="sd-icon-btn" title="فتح"><i class="fas fa-arrow-left"></i></button>
            @else
                <button type="submit" class="sd-icon-btn" title="تعليم كمقروء"><i class="fas fa-check"></i></button>
            @endif
        </form>
    @endif
</div>
