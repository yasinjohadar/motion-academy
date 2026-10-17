{{--
    Circular progress in the brand gradient.

    Usage: <x-frontend.progress-ring :percent="64" />
           <x-frontend.progress-ring :percent="100" :size="72" />
--}}
@props([
    'percent' => 0,
    'size' => 56,
])

@php
    $percent = max(0, min(100, (int) $percent));
    $radius = 16;
    $circumference = 2 * M_PI * $radius;
    $gradientId = 'sd-ring-'.\Illuminate\Support\Str::random(6);
@endphp

<span {{ $attributes->class(['sd-ring', 'is-done' => $percent === 100]) }} style="--size: {{ $size }}px" role="img" aria-label="نسبة الإنجاز {{ $percent }}%">
    <svg viewBox="0 0 40 40" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#02ad8f" />
                <stop offset="100%" stop-color="#33a4da" />
            </linearGradient>
        </defs>
        <circle class="sd-ring-track" cx="20" cy="20" r="{{ $radius }}" />
        <circle class="sd-ring-bar" cx="20" cy="20" r="{{ $radius }}" stroke="url(#{{ $gradientId }})"
            stroke-dasharray="{{ round($circumference, 2) }}"
            stroke-dashoffset="{{ round($circumference * (1 - $percent / 100), 2) }}" />
    </svg>
    <span class="sd-ring-value en-text">
        @if ($percent === 100)
            <i class="fas fa-check"></i>
        @else
            {{ $percent }}<small>%</small>
        @endif
    </span>
</span>
