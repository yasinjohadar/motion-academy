<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The canonical, unique phone number used to find an account for OTP login and
 * WhatsApp password reset. `phone` stays as the free-form display value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_e164', 20)->nullable()->unique()->after('phone');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_e164');
        });

        // Backfill from existing numbers. A number that is invalid, or already taken
        // by another account, stays empty rather than failing the migration — those
        // users add it again from their profile.
        $taken = [];

        DB::table('users')->whereNotNull('phone')->orderBy('id')->select(['id', 'phone'])
            ->chunk(500, function ($users) use (&$taken) {
                foreach ($users as $user) {
                    $canonical = PhoneNumber::fromAny($user->phone);

                    if ($canonical === null || isset($taken[$canonical])) {
                        continue;
                    }

                    $taken[$canonical] = true;
                    DB::table('users')->where('id', $user->id)->update(['phone_e164' => $canonical]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_e164']);
            $table->dropColumn(['phone_e164', 'phone_verified_at']);
        });
    }
};
