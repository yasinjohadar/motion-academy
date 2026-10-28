<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use App\Services\Frontend\TeamSettings;
use Illuminate\Database\Seeder;

/**
 * The five team members the home page shipped with. Matched on name, so
 * re-running refreshes them without duplicating.
 *
 * The original cards linked every social button to "#"; these profile URLs are
 * placeholders to replace from the panel.
 */
class TeamMemberSeeder extends Seeder
{
    public function run(TeamSettings $settings): void
    {
        foreach ($this->members() as $order => $member) {
            TeamMember::query()->updateOrCreate(
                ['name' => $member['name']],
                [...$member, 'is_active' => true, 'sort_order' => $order + 1],
            );
        }

        $settings->seedDefaults();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function members(): array
    {
        return [
            [
                'name' => 'م. أحمد سعيد',
                'title' => 'Senior Front-end Engineer',
                'bio' => 'خبير في بناء وتطوير واجهات الويب الحديثة بأحدث التقنيات.',
                'icon' => 'fas fa-user-tie',
                'color' => '#02ad8f',
                'rating' => 4.9,
                'social_links' => [
                    ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/'],
                    ['platform' => 'youtube', 'url' => 'https://www.youtube.com/'],
                ],
            ],
            [
                'name' => 'سارة محمد',
                'title' => 'UX/UI Designer',
                'bio' => 'متخصصة في تصميم تجربة المستخدم وواجهات الجوال الحديثة.',
                'icon' => 'fas fa-paint-brush',
                'color' => '#33a4da',
                'rating' => 4.8,
                'social_links' => [
                    ['platform' => 'behance', 'url' => 'https://www.behance.net/'],
                    ['platform' => 'dribbble', 'url' => 'https://dribbble.com/'],
                ],
            ],
            [
                'name' => 'عمر مصطفى',
                'title' => 'Machine Learning Engineer',
                'bio' => 'شغوف بعلوم البيانات والذكاء الاصطناعي وبناء نماذج توليدية متقدمة.',
                'icon' => 'fas fa-robot',
                'color' => '#1a83c1',
                'rating' => 4.9,
                'social_links' => [
                    ['platform' => 'github', 'url' => 'https://github.com/'],
                    ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/'],
                ],
            ],
            [
                'name' => 'طارق زياد',
                'title' => 'Digital Marketing Expert',
                'bio' => 'خبير في التسويق الرقمي وإدارة الحملات الإعلانية لتحقيق أعلى عائد.',
                'icon' => 'fas fa-chart-pie',
                'color' => '#45bec4',
                'rating' => 4.5,
                'social_links' => [
                    ['platform' => 'x', 'url' => 'https://x.com/'],
                    ['platform' => 'linkedin', 'url' => 'https://www.linkedin.com/'],
                ],
            ],
            [
                'name' => 'م. خالد أحمد',
                'title' => 'Flutter / Mobile Developer',
                'bio' => 'متخصص في تطوير تطبيقات الجوال بـ Flutter وDart للـ iOS والـ Android.',
                'icon' => 'fas fa-mobile-alt',
                'color' => '#1a83c1',
                'rating' => 5.0,
                'social_links' => [
                    ['platform' => 'github', 'url' => 'https://github.com/'],
                    ['platform' => 'youtube', 'url' => 'https://www.youtube.com/'],
                ],
            ],
        ];
    }
}
