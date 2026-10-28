<article class="team-card" style="--team-color: {{ $member->accentColor() }};">
    <div class="team-card-avatar">
        @if ($avatar = $member->avatarUrl())
            <img src="{{ $avatar }}" alt="{{ $member->name }}" class="team-card-avatar-img" loading="lazy">
        @else
            <span class="team-card-avatar-inner"><i class="{{ $member->icon ?: \App\Models\TeamMember::DEFAULT_ICON }}"></i></span>
        @endif
    </div>
    <h3 class="team-card-name">
        @if ($member->profile_url)
            <a href="{{ $member->profile_url }}" class="text-reset text-decoration-none">{{ $member->name }}</a>
        @else
            {{ $member->name }}
        @endif
    </h3>
    @if ($member->title)
        <p class="team-card-role en-text">{{ $member->title }}</p>
    @endif
    @if ($teamSettings['show_rating'] && $member->rating !== null)
        <div class="team-card-rating en-text" aria-label="تقييم {{ (float) $member->rating }} من 5">
            <span class="team-rating-stars">
                @foreach ($member->starIcons() as $star)<i class="{{ $star }}"></i>@endforeach
            </span>
            <span class="team-rating-value">{{ number_format((float) $member->rating, 1) }}</span>
        </div>
    @endif
    @if ($member->bio)
        <p class="team-card-bio">{{ $member->bio }}</p>
    @endif
    @if ($teamSettings['show_social'] && ($buttons = $member->socialButtons()))
        <div class="team-card-social">
            @foreach ($buttons as $button)
                <a href="{{ $button['url'] }}" class="team-social-btn" aria-label="{{ $button['label'] }}" target="_blank" rel="noopener noreferrer"><i class="{{ $button['icon'] }}"></i></a>
            @endforeach
        </div>
    @endif
</article>
