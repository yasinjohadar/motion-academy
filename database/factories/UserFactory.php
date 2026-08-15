<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @var list<string>
     */
    protected array $arabicFirst = [
        'أحمد', 'محمد', 'علي', 'حسن', 'يوسف', 'عمر', 'خالد', 'سامي', 'فادي', 'رامي',
        'نور', 'سارة', 'لينا', 'ريم', 'هبة', 'دانية', 'جنى', 'ملك', 'ياسمين', 'تالا',
    ];

    /**
     * @var list<string>
     */
    protected array $arabicLast = [
        'الخطيب', 'الحسن', 'العلي', 'الشمري', 'الدوسري', 'القحطاني', 'الزهراني',
        'المطيري', 'العنزي', 'الغامدي', 'السعد', 'الناصر', 'الجابر', 'الفهيد',
    ];

    /**
     * @var list<string>
     */
    protected array $englishFirst = [
        'Ahmed', 'Mohammed', 'Ali', 'Hassan', 'Yousef', 'Omar', 'Khaled', 'Sami', 'Fadi', 'Rami',
        'Noor', 'Sara', 'Lina', 'Reem', 'Hiba', 'Dania', 'Jana', 'Malak', 'Yasmine', 'Tala',
    ];

    /**
     * @var list<string>
     */
    protected array $englishLast = [
        'Alkhateeb', 'Alhassan', 'Alali', 'Alshammari', 'Aldosari', 'Alqahtani', 'Alzahrani',
        'Almutairi', 'Alanazi', 'Alghamdi', 'Alsaad', 'Alnasser', 'Aljaber', 'Alfuhaid',
    ];

    public function definition(): array
    {
        $index = fake()->numberBetween(0, count($this->arabicFirst) - 1);
        $lastIndex = fake()->numberBetween(0, count($this->arabicLast) - 1);

        $nameAr = $this->arabicFirst[$index].' '.$this->arabicLast[$lastIndex];
        $nameEn = $this->englishFirst[$index].' '.$this->englishLast[$lastIndex];

        return [
            'name' => $nameAr,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'email' => fake()->unique()->safeEmail(),
            'phone' => '05'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Present but null, so the model carries the same attribute set as a row
            // read back from the database. Omitting them means anything reading
            // `last_login_at` off a factory-made user throws under
            // Model::preventAccessingMissingAttributes.
            'last_login_at' => null,
            'last_login_ip' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
