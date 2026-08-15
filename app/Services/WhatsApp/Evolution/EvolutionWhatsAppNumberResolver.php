<?php

namespace App\Services\WhatsApp\Evolution;

use App\Support\WapiPhoneNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * Resolve the best WhatsApp recipient digits/JID via Evolution whatsappNumbers check.
 */
class EvolutionWhatsAppNumberResolver
{
    public function __construct(
        private EvolutionService $evolutionService,
        private EvolutionRotatingSendService $rotatingSendService,
    ) {}

    /**
     * @return array{digits: string, jid: ?string, exists: ?bool, checked: bool}
     */
    public function resolve(string $phoneDigitsOrE164): array
    {
        $digits = WapiPhoneNormalizer::normalize($phoneDigitsOrE164);
        $fallback = [
            'digits' => $digits,
            'jid' => null,
            'exists' => null,
            'checked' => false,
        ];

        if ($digits === '' || ! WapiPhoneNormalizer::isValidE164Digits($digits)) {
            return $fallback;
        }

        try {
            $instanceName = $this->pickInstanceName();
            if ($instanceName === '') {
                return $fallback;
            }

            $client = $this->evolutionService->clientFor(null, $instanceName);
            $results = $client->whatsappNumbers($instanceName, [$digits]);
            $match = $this->findResultForNumber($results, $digits);

            if ($match === null) {
                Log::channel('whatsapp')->warning('Evolution whatsappNumbers returned no match', [
                    'digits' => $digits,
                    'instance' => $instanceName,
                    'results_count' => count($results),
                ]);

                return $fallback;
            }

            $exists = filter_var($match['exists'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $jid = isset($match['jid']) ? trim((string) $match['jid']) : null;
            $resolvedDigits = $digits;

            if ($jid && preg_match('/^(\d+)@/', $jid, $m) === 1) {
                $fromJid = $m[1];
                if (WapiPhoneNormalizer::isValidE164Digits($fromJid)) {
                    $resolvedDigits = $fromJid;
                }
            } elseif (! empty($match['number'])) {
                $fromNumber = WapiPhoneNormalizer::normalize((string) $match['number']);
                if (WapiPhoneNormalizer::isValidE164Digits($fromNumber)) {
                    $resolvedDigits = $fromNumber;
                }
            }

            return [
                'digits' => $resolvedDigits,
                'jid' => $jid !== '' ? $jid : null,
                'exists' => $exists,
                'checked' => true,
            ];
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Evolution whatsappNumbers check failed', [
                'digits' => $digits,
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    private function pickInstanceName(): string
    {
        $pool = $this->rotatingSendService->isRotationActive()
            ? app(EvolutionInstanceRotator::class)->orderedPoolForFailover()
            : collect();

        if ($pool->isNotEmpty()) {
            return (string) $pool->first()->instance_name;
        }

        return $this->rotatingSendService->fallbackInstanceName();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    private function findResultForNumber(array $results, string $digits): ?array
    {
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowNumber = WapiPhoneNormalizer::normalize((string) ($row['number'] ?? ''));
            $jidDigits = '';
            if (! empty($row['jid']) && preg_match('/^(\d+)@/', (string) $row['jid'], $m) === 1) {
                $jidDigits = $m[1];
            }

            if ($rowNumber === $digits || $jidDigits === $digits) {
                return $row;
            }
        }

        return $results[0] ?? null;
    }
}
