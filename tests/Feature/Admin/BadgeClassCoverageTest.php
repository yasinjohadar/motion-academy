<?php

namespace Tests\Feature\Admin;

use App\Enums\NotificationCategory;
use App\Enums\NotificationLevel;
use Tests\TestCase;

/**
 * Every badge class an enum hands to the frontend must actually exist in the stylesheet.
 *
 * This gap is invisible in every other way: PHP is happy, the build is happy, the tests
 * are happy, and the only symptom is a badge that renders with no colour — which nobody
 * notices until a screenshot. Three classes (`info`, `success`, `danger`) were undefined
 * this way while being returned by two enums.
 */
class BadgeClassCoverageTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    protected function definedClasses(): array
    {
        $css = '';

        foreach (glob(resource_path('css/*.css')) as $file) {
            $css .= file_get_contents($file);
        }

        preg_match_all('/\.(admin-badge-[a-z0-9-]+)/', $css, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<string, string>
     */
    protected function referencedClasses(): array
    {
        $referenced = [];

        foreach (NotificationCategory::cases() as $case) {
            $referenced["NotificationCategory::{$case->name}"] = $case->badgeClass();
        }

        foreach (NotificationLevel::cases() as $case) {
            $referenced["NotificationLevel::{$case->name}"] = $case->badgeClass();
        }

        return $referenced;
    }

    public function test_every_badge_class_returned_by_an_enum_is_defined_in_css(): void
    {
        $defined = $this->definedClasses();
        $missing = [];

        foreach ($this->referencedClasses() as $source => $class) {
            if (! in_array($class, $defined, true)) {
                $missing[] = "{$source} → .{$class}";
            }
        }

        $this->assertSame([], $missing, "These badge classes are used but never styled:\n".implode("\n", $missing));
    }

    public function test_the_probe_can_actually_find_classes(): void
    {
        // Guards the regex itself: if it silently matched nothing, the test above would
        // pass only because `$defined` was empty — and prove nothing at all.
        $this->assertNotEmpty($this->definedClasses());
        $this->assertContains('admin-badge-muted', $this->definedClasses());
    }

    public function test_every_enum_actually_returns_a_badge_class(): void
    {
        foreach ($this->referencedClasses() as $source => $class) {
            $this->assertMatchesRegularExpression(
                '/^admin-badge-[a-z]+$/',
                $class,
                "{$source} returned an unexpected badge class."
            );
        }
    }
}
