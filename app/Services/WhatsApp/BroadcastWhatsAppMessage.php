<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Support\WhatsAppRecipientNormalizer;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BroadcastWhatsAppMessage
{
    public function __construct(
        protected SendWhatsAppMessage $sendService,
    ) {}

    public function getEffectivePhone(User $user): string
    {
        $phone = trim((string) ($user->phone ?? ''));
        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/\s+/', '', $phone) ?? '';
        if ($phone !== '' && ! str_starts_with($phone, '+')) {
            $phone = '+'.ltrim($phone, '0');
        }

        return $phone;
    }

    public function hasValidPhone(User $user): bool
    {
        $phone = $this->getEffectivePhone($user);
        if ($phone === '') {
            return false;
        }

        try {
            WhatsAppRecipientNormalizer::normalize('evolution', $phone);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * @param  array<int, int>  $userIds
     * @return Collection<int, array{user_id: ?int, phone: string, name: ?string}>
     */
    public function resolveAudience(array $userIds = [], string $manualPhones = ''): Collection
    {
        $recipients = collect();

        if ($userIds !== []) {
            User::query()
                ->whereIn('id', $userIds)
                ->get(['id', 'name', 'phone'])
                ->each(function (User $user) use ($recipients) {
                    if (! $this->hasValidPhone($user)) {
                        return;
                    }

                    $phone = WhatsAppRecipientNormalizer::normalize(
                        'evolution',
                        $this->getEffectivePhone($user)
                    );

                    $recipients->push([
                        'user_id' => $user->id,
                        'phone' => $phone,
                        'name' => $user->name,
                    ]);
                });
        }

        foreach ($this->parseManualPhones($manualPhones) as $phone) {
            try {
                $normalized = WhatsAppRecipientNormalizer::normalize('evolution', $phone);
            } catch (InvalidArgumentException) {
                continue;
            }

            $recipients->push([
                'user_id' => null,
                'phone' => $normalized,
                'name' => null,
            ]);
        }

        return $recipients
            ->unique('phone')
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function parseManualPhones(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    public function replacePlaceholders(string $template, ?User $user = null, ?string $phone = null): string
    {
        $replacements = [
            '{name}' => $user?->name ?? '',
            '{email}' => $user?->email ?? '',
            '{phone}' => $phone ?? $this->getEffectivePhone($user ?? new User),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
}
