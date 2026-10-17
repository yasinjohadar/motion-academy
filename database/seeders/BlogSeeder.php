<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fifteen demo articles in five categories (three each), with tags.
 *
 * Safe to run repeatedly: categories, tags and posts are matched by slug.
 * Reading time and post counts are set here because DatabaseSeeder runs
 * WithoutModelEvents, which skips the model hooks that normally fill them.
 */
class BlogSeeder extends Seeder
{
    /**
     * @var array<string, array{name: string, description: string, color: string, icon: string}>
     */
    private const CATEGORIES = [
        'learning-tips' => ['name' => 'نصائح التعلّم', 'description' => 'أساليب عملية لتتعلم أسرع وتتذكر أكثر.', 'color' => '#02ad8f', 'icon' => 'ri-lightbulb-line'],
        'programming' => ['name' => 'البرمجة', 'description' => 'مقالات في تطوير الويب والتطبيقات ولغات البرمجة.', 'color' => '#1a83c1', 'icon' => 'ri-code-s-slash-line'],
        'design' => ['name' => 'التصميم والموشن', 'description' => 'التصميم الجرافيكي وتحريك الرسوميات وصناعة الفيديو.', 'color' => '#33a4da', 'icon' => 'ri-palette-line'],
        'career' => ['name' => 'المسار المهني', 'description' => 'العمل الحر وبناء ملف الأعمال والاستعداد لسوق العمل.', 'color' => '#45bec4', 'icon' => 'ri-briefcase-line'],
        'platform-news' => ['name' => 'أخبار المنصة', 'description' => 'الجديد في المنصة من دورات وميزات وفعاليات.', 'color' => '#06b59c', 'icon' => 'ri-megaphone-line'],
    ];

    /**
     * @var array<string, string>
     */
    private const TAGS = [
        'beginners' => 'للمبتدئين',
        'productivity' => 'الإنتاجية',
        'web' => 'تطوير الويب',
        'ai' => 'الذكاء الاصطناعي',
        'after-effects' => 'After Effects',
        'freelancing' => 'العمل الحر',
        'portfolio' => 'ملف الأعمال',
        'updates' => 'تحديثات',
    ];

    /**
     * title, slug, category, tags, excerpt, sections (heading => paragraph), featured, days ago.
     *
     * @var array<int, array{title: string, slug: string, category: string, tags: array<int, string>, excerpt: string, sections: array<string, string>, featured?: bool, days: int}>
     */
    private const POSTS = [
        [
            'title' => 'كيف تبني عادة تعلّم يومية لا تنقطع',
            'slug' => 'build-a-daily-learning-habit',
            'category' => 'learning-tips',
            'tags' => ['productivity', 'beginners'],
            'excerpt' => 'الاستمرارية أهم من عدد الساعات. إليك طريقة بسيطة لتجعل التعلّم جزءاً ثابتاً من يومك.',
            'sections' => [
                'ابدأ بجلسة قصيرة' => 'عشرون دقيقة يومياً أفضل من خمس ساعات مرة في الأسبوع. الجلسة القصيرة سهلة البدء، ومع الوقت تصبح عادة لا تحتاج إلى قرار.',
                'اربطها بعادة موجودة' => 'اختر وقتاً ثابتاً بعد نشاط تقوم به يومياً، مثل قهوة الصباح، حتى يتذكرك الروتين بدل أن تتذكره أنت.',
                'تابع تقدّمك' => 'سجّل كل يوم تعلّمت فيه. رؤية سلسلة الأيام المتتالية دافع قوي لعدم قطعها.',
            ],
            'featured' => true,
            'days' => 2,
        ],
        [
            'title' => 'تقنية التكرار المتباعد: تذكّر ما تتعلمه لفترة أطول',
            'slug' => 'spaced-repetition-explained',
            'category' => 'learning-tips',
            'tags' => ['productivity'],
            'excerpt' => 'لماذا ننسى معظم ما نتعلمه خلال أيام؟ وكيف تساعدك المراجعة على فترات متباعدة في تثبيت المعلومة؟',
            'sections' => [
                'منحنى النسيان' => 'تتلاشى المعلومة الجديدة بسرعة إن لم نعد إليها. المراجعة في الوقت المناسب تعيد تثبيتها وتبطئ نسيانها في كل مرة.',
                'كيف تطبّقها' => 'راجع الدرس بعد يوم، ثم بعد ثلاثة أيام، ثم بعد أسبوع. البطاقات التعليمية طريقة سهلة لتنظيم ذلك.',
                'اختبر نفسك' => 'استرجاع المعلومة من الذاكرة أقوى أثراً من إعادة قراءتها. اسأل نفسك قبل أن تنظر إلى الإجابة.',
            ],
            'days' => 9,
        ],
        [
            'title' => 'خمسة أخطاء شائعة عند التعلّم عبر الإنترنت',
            'slug' => 'common-online-learning-mistakes',
            'category' => 'learning-tips',
            'tags' => ['beginners', 'productivity'],
            'excerpt' => 'التعلّم الذاتي يمنحك الحرية، لكنه يحتاج إلى انضباط. تجنّب هذه الأخطاء لتستفيد من كل دورة.',
            'sections' => [
                'المشاهدة دون تطبيق' => 'مشاهدة الدرس تعطي شعوراً بالفهم، لكن التطبيق هو ما يحوّله إلى مهارة. نفّذ كل مثال بنفسك.',
                'الانتقال بين الدورات' => 'البدء في دورات كثيرة دون إنهاء أيٍّ منها يشتت الجهد. أكمل دورة واحدة قبل الانتقال لغيرها.',
                'تجاهل المراجعة' => 'خصص وقتاً لمراجعة ما سبق قبل كل درس جديد، فالبناء على أساس ضعيف يضيّع الوقت لاحقاً.',
            ],
            'days' => 21,
        ],
        [
            'title' => 'من أين تبدأ تعلّم البرمجة في 2026؟',
            'slug' => 'where-to-start-programming',
            'category' => 'programming',
            'tags' => ['beginners', 'web'],
            'excerpt' => 'كثرة اللغات والأدوات تربك المبتدئ. هذه خارطة طريق مختصرة لتبدأ بثقة.',
            'sections' => [
                'اختر هدفاً قبل اللغة' => 'هل تريد بناء مواقع أم تطبيقات جوال أم تحليل بيانات؟ الهدف يحدد اللغة المناسبة لك، لا العكس.',
                'تعلّم الأساسيات جيداً' => 'المتغيرات والشروط والحلقات والدوال مفاهيم مشتركة بين كل اللغات. إتقانها يجعل تعلّم أي لغة لاحقة أسهل.',
                'ابنِ مشاريع صغيرة' => 'آلة حاسبة، قائمة مهام، موقع شخصي. المشاريع الصغيرة تكشف ما لم تفهمه بعد وتبني ملف أعمالك.',
            ],
            'featured' => true,
            'days' => 4,
        ],
        [
            'title' => 'دليل المبتدئ إلى HTML وCSS',
            'slug' => 'html-css-beginners-guide',
            'category' => 'programming',
            'tags' => ['web', 'beginners'],
            'excerpt' => 'كل موقع على الإنترنت يبدأ من هنا. تعرّف على دور كل من HTML وCSS وكيف يعملان معاً.',
            'sections' => [
                'HTML: هيكل الصفحة' => 'تحدد HTML محتوى الصفحة وبنيتها: العناوين والفقرات والصور والروابط. فكّر فيها كهيكل البناء.',
                'CSS: الشكل والتنسيق' => 'تتحكم CSS في الألوان والخطوط والمسافات وتوزيع العناصر، وتجعل الصفحة تتكيف مع الشاشات المختلفة.',
                'تمرين سريع' => 'أنشئ صفحة تعريفية عنك تحتوي على صورة وفقرة وقائمة بمهاراتك، ثم نسّقها بلونين وخط واحد.',
            ],
            'days' => 12,
        ],
        [
            'title' => 'كيف يغيّر الذكاء الاصطناعي طريقة كتابة الكود',
            'slug' => 'ai-and-writing-code',
            'category' => 'programming',
            'tags' => ['ai', 'productivity'],
            'excerpt' => 'المساعدات الذكية أصبحت جزءاً من يوم المبرمج. كيف تستفيد منها دون أن تفقد فهمك لما تكتبه؟',
            'sections' => [
                'مساعد لا بديل' => 'تسرّع الأدوات الذكية المهام المتكررة واقتراح الحلول، لكن مسؤولية فهم الكود ومراجعته تبقى عليك.',
                'اسأل بوضوح' => 'كلما كان وصفك للمشكلة دقيقاً ومعه السياق الكافي، كانت الاقتراحات أفضل وأقرب لما تحتاجه.',
                'راجع واختبر' => 'لا تقبل أي كود دون قراءته وتشغيل الاختبارات عليه. الأخطاء الصغيرة قد تمر بسهولة.',
            ],
            'days' => 30,
        ],
        [
            'title' => 'مبادئ التصميم الأساسية لكل مبتدئ',
            'slug' => 'basic-design-principles',
            'category' => 'design',
            'tags' => ['beginners'],
            'excerpt' => 'التباين والمحاذاة والتكرار والتقارب: أربعة مبادئ تجعل تصاميمك أوضح وأكثر احترافية.',
            'sections' => [
                'التباين' => 'يبرز التباين العنصر الأهم في التصميم. استخدم اختلاف الحجم أو اللون أو الوزن لتوجيه عين المشاهد.',
                'المحاذاة' => 'العناصر المحاذاة على خطوط واضحة تبدو منظمة ومترابطة، حتى لو كانت متباعدة.',
                'التكرار والتقارب' => 'كرّر الألوان والخطوط لتوحيد التصميم، وقرّب العناصر المرتبطة ببعضها ليفهم المشاهد العلاقة بينها.',
            ],
            'featured' => true,
            'days' => 6,
        ],
        [
            'title' => 'خطواتك الأولى في الموشن جرافيك',
            'slug' => 'first-steps-in-motion-graphics',
            'category' => 'design',
            'tags' => ['after-effects', 'beginners'],
            'excerpt' => 'الحركة تضيف حياة للتصميم. تعرّف على المفاهيم التي تحتاجها قبل أن تفتح برنامج التحريك.',
            'sections' => [
                'الإطارات المفتاحية' => 'كل حركة تبدأ بإطارين: حالة البداية وحالة النهاية، ويحسب البرنامج ما بينهما.',
                'منحنيات السرعة' => 'الحركة الطبيعية تتسارع وتتباطأ. ضبط منحنى السرعة هو ما يجعل الحركة سلسة بدل أن تبدو آلية.',
                'ابدأ بالنصوص' => 'تحريك نص بسيط تمرين ممتاز لفهم التوقيت والإيقاع قبل الانتقال لمشاهد معقدة.',
            ],
            'days' => 15,
        ],
        [
            'title' => 'كيف تختار ألوان تصميمك بثقة',
            'slug' => 'choosing-colors-with-confidence',
            'category' => 'design',
            'tags' => ['beginners'],
            'excerpt' => 'اختيار الألوان ليس ذوقاً فقط. قواعد بسيطة تساعدك على بناء لوحة ألوان متناسقة.',
            'sections' => [
                'قاعدة 60-30-10' => 'لون أساسي يغطي معظم المساحة، ولون ثانوي يدعمه، ولون مميز صغير يجذب الانتباه للعناصر المهمة.',
                'التباين وسهولة القراءة' => 'تأكد أن النص واضح على الخلفية، خاصة للأشخاص ضعيفي البصر. التباين الكافي جزء من التصميم الجيد.',
                'ابدأ من مرجع' => 'استلهم لوحات ألوان من الطبيعة أو الصور، ثم عدّلها لتناسب هوية مشروعك.',
            ],
            'days' => 40,
        ],
        [
            'title' => 'كيف تبني ملف أعمال يلفت انتباه العملاء',
            'slug' => 'build-a-standout-portfolio',
            'category' => 'career',
            'tags' => ['portfolio', 'freelancing'],
            'excerpt' => 'ملف أعمالك هو أول ما يراه العميل. اجعله يحكي قصة مهارتك لا مجرد قائمة صور.',
            'sections' => [
                'الجودة قبل الكمية' => 'ثلاثة مشاريع قوية أفضل من عشرين مشروعاً متوسطاً. اختر أعمالك التي تمثّل المجال الذي تريد العمل فيه.',
                'اشرح طريقة تفكيرك' => 'لكل مشروع اكتب المشكلة، والحل الذي اخترته، والنتيجة. العميل يبحث عن طريقة تفكيرك بقدر النتيجة.',
                'حدّثه باستمرار' => 'أضف أحدث أعمالك واحذف القديمة التي لم تعد تمثّل مستواك.',
            ],
            'days' => 8,
        ],
        [
            'title' => 'دليلك لبدء العمل الحر عبر الإنترنت',
            'slug' => 'getting-started-with-freelancing',
            'category' => 'career',
            'tags' => ['freelancing'],
            'excerpt' => 'العمل الحر يمنحك المرونة، لكنه يحتاج إلى تخطيط. هذه أهم الخطوات للبداية.',
            'sections' => [
                'حدد خدمتك بدقة' => 'بدل "أصمم كل شيء"، اعرض خدمة واضحة مثل "تصميم هويات بصرية للمطاعم". التخصص يسهّل وصول العميل إليك.',
                'سعّر بعقلانية' => 'ابحث عن متوسط الأسعار في مجالك، واحسب وقتك وتكاليفك، ثم ارفع أسعارك تدريجياً مع خبرتك.',
                'التواصل الواضح' => 'اتفق على التفاصيل والمواعيد كتابياً قبل البدء، وأبقِ العميل على اطلاع بالتقدم.',
            ],
            'days' => 18,
        ],
        [
            'title' => 'المهارات الشخصية التي يبحث عنها أصحاب العمل',
            'slug' => 'soft-skills-employers-want',
            'category' => 'career',
            'tags' => ['productivity'],
            'excerpt' => 'المهارة التقنية تفتح الباب، لكن المهارات الشخصية هي ما يبقيك ويطوّرك في العمل.',
            'sections' => [
                'التواصل' => 'القدرة على شرح فكرتك بوضوح، والاستماع للآخرين، وكتابة رسائل مختصرة ومفهومة.',
                'حل المشكلات' => 'تقسيم المشكلة الكبيرة إلى أجزاء صغيرة، والبحث عن الحل بدل انتظار التعليمات.',
                'التعلّم المستمر' => 'الأدوات تتغير بسرعة، ومن يتعلّم باستمرار يبقى مطلوباً في سوق العمل.',
            ],
            'days' => 35,
        ],
        [
            'title' => 'أطلقنا دورات جديدة في التصميم والبرمجة',
            'slug' => 'new-courses-launched',
            'category' => 'platform-news',
            'tags' => ['updates'],
            'excerpt' => 'أضفنا مجموعة دورات جديدة لتغطي مجالات أكثر وتناسب كل المستويات.',
            'sections' => [
                'ما الجديد' => 'دورات جديدة في تطوير الويب والموشن جرافيك واللغة الإنجليزية، مع دروس قصيرة وتطبيقات عملية.',
                'دروس مجانية للمعاينة' => 'يمكنك مشاهدة الدرس الأول من كل دورة مجاناً قبل أن تقرر التسجيل.',
                'تابعنا' => 'نضيف دورات جديدة باستمرار، فتابع صفحة الدورات والمدونة لتصلك آخر الأخبار.',
            ],
            'featured' => true,
            'days' => 1,
        ],
        [
            'title' => 'تحسينات جديدة على تجربة التعلّم في المنصة',
            'slug' => 'learning-experience-improvements',
            'category' => 'platform-news',
            'tags' => ['updates'],
            'excerpt' => 'عملنا على تسهيل تصفح الدورات والبحث عنها لتصل إلى ما تحتاجه بسرعة.',
            'sections' => [
                'بحث أسرع' => 'أصبح البحث يعرض النتائج فوراً أثناء الكتابة، مع فلاتر حسب التصنيف والمستوى والسعر.',
                'صفحات دورات أوضح' => 'تعرض صفحة الدورة الآن المنهج كاملاً مقسّماً بالأقسام، مع مدة كل درس.',
                'المزيد قادم' => 'نعمل على ميزات جديدة لمتابعة تقدّمك في الدورات، وسنعلن عنها قريباً.',
            ],
            'days' => 11,
        ],
        [
            'title' => 'مرحباً بكم في مدونة المنصة',
            'slug' => 'welcome-to-our-blog',
            'category' => 'platform-news',
            'tags' => ['updates', 'beginners'],
            'excerpt' => 'أطلقنا المدونة لتكون مساحة للمعرفة إلى جانب الدورات: مقالات قصيرة ونصائح عملية كل أسبوع.',
            'sections' => [
                'ماذا ستجد هنا' => 'نصائح للتعلّم، ومقالات في البرمجة والتصميم، وإرشادات للعمل الحر وبناء مسارك المهني.',
                'مقالات مرتبطة بالدورات' => 'كثير من المقالات تكمّل دروس الدورات، فتجد فيها أمثلة إضافية وتمارين للتطبيق.',
                'شاركنا رأيك' => 'نرحب باقتراحاتك للمواضيع التي تحب أن نكتب عنها، فالمدونة تُكتب من أجلك.',
            ],
            'days' => 45,
        ],
    ];

    public function run(): void
    {
        $author = User::query()->where('email', 'admin@example.com')->first()
            ?? User::query()->orderBy('id')->first();

        $categories = [];
        $order = 1;
        foreach (self::CATEGORIES as $slug => $category) {
            $categories[$slug] = BlogCategory::withTrashed()->updateOrCreate(['slug' => $slug], [
                ...$category,
                'meta_title' => $category['name'],
                'meta_description' => $category['description'],
                'is_active' => true,
                'order' => $order++,
            ]);
            $categories[$slug]->restore();
        }

        $tags = [];
        foreach (self::TAGS as $slug => $name) {
            $tags[$slug] = BlogTag::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'is_active' => true]);
        }

        foreach (self::POSTS as $index => $definition) {
            $content = $this->content($definition['excerpt'], $definition['sections']);

            $post = BlogPost::query()->updateOrCreate(['slug' => $definition['slug']], [
                'title' => $definition['title'],
                'excerpt' => $definition['excerpt'],
                'content' => $content,
                'author_id' => $author?->id,
                'blog_category_id' => $categories[$definition['category']]->id,
                'meta_title' => $definition['title'],
                'meta_description' => $definition['excerpt'],
                'meta_keywords' => implode('، ', array_map(fn (string $tag) => self::TAGS[$tag], $definition['tags'])),
                'status' => 'published',
                'published_at' => now()->subDays($definition['days'])->setTime(10, 0),
                'is_featured' => $definition['featured'] ?? false,
                'is_indexable' => true,
                'language' => 'ar',
                'reading_time' => max(1, (int) ceil(str_word_count(strip_tags($content), 0, 'ءآأؤإئابةتثجحخدذرزسشصضطظعغفقكلمنهوىي') / 200)),
                'views_count' => 40 + ($index * 37) % 400,
            ]);

            $post->tags()->sync(array_map(fn (string $slug) => $tags[$slug]->id, $definition['tags']));
        }

        foreach ($categories as $category) {
            $category->update(['posts_count' => $category->posts()->count()]);
        }
        foreach ($tags as $tag) {
            $tag->update(['posts_count' => $tag->posts()->count()]);
        }
    }

    /**
     * @param  array<string, string>  $sections
     */
    private function content(string $intro, array $sections): string
    {
        $html = '<p>'.e($intro).'</p>';

        foreach ($sections as $heading => $paragraph) {
            $html .= '<h2>'.e($heading).'</h2><p>'.e($paragraph).'</p>';
        }

        return $html.'<p>إن وجدت هذا المقال مفيداً، تصفّح دوراتنا لتطبيق ما تعلّمته خطوة بخطوة.</p>';
    }
}
