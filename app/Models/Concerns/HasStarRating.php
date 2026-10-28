<?php

namespace App\Models\Concerns;

/**
 * Renders a 0–5 `rating` column as five Font Awesome star icons.
 */
trait HasStarRating
{
    /**
     * Star icons for the rating rounded to the nearest half: full, half and
     * empty, always five in total.
     *
     * @return array<int, string>
     */
    public function starIcons(): array
    {
        $rating = max(0, min(5, round((float) $this->rating * 2) / 2));
        $full = (int) floor($rating);
        $half = $rating - $full >= 0.5 ? 1 : 0;

        return [
            ...array_fill(0, $full, 'fas fa-star'),
            ...array_fill(0, $half, 'fas fa-star-half-alt'),
            ...array_fill(0, 5 - $full - $half, 'far fa-star'),
        ];
    }
}
