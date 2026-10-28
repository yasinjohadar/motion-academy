<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            CourseCategorySeeder::class,
            CourseSeeder::class,
            HeroSlideSeeder::class,
            TestimonialSeeder::class,
            TeamMemberSeeder::class,
        ]);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدير النظام',
                'name_ar' => 'مدير النظام',
                'name_en' => 'System Admin',
                'phone' => '0500000000',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $admin->syncPermissions(Permission::all());
        $admin->syncRoles(['admin']);

        // After the admin exists: the demo articles are attributed to them.
        $this->call(BlogSeeder::class);

        if (User::query()->count() < 10) {
            // Demo learners: every account is an admin or a student.
            User::factory(50)->create()->each->assignRole('student');
        }
    }
}
