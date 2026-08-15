<?php

namespace App\Support;

use App\Services\WhatsApp\Evolution\EvolutionApiException;

class WhatsAppSendErrorMessage
{
    public static function fromThrowable(\Throwable $e): string
    {
        if ($e instanceof EvolutionApiException) {
            return $e->userMessage();
        }

        $message = $e->getMessage();

        if (str_contains($message, 'cURL error 28') || str_contains($message, 'Resolving timed out')) {
            $host = self::extractHost($message);

            return 'تعذر الاتصال بخادم Evolution API'
                .($host ? " ({$host})" : '')
                .'. تحقق من عنوان Evolution في الإعدادات العامة، وتأكد أن النطاق يُحلّ (DNS) ويعمل من جهاز السيرفر المحلي.';
        }

        if (str_contains($message, 'cURL error 7') || str_contains($message, 'Failed to connect')) {
            return 'تعذر الوصول إلى خادم Evolution API. تحقق من أن الخادم يعمل وأن عنوان URL صحيح.';
        }

        if (str_contains($message, 'Evolution API: Bad Request')) {
            return 'رفض Evolution API الطلب (Bad Request). تحقق من صحة رقم الواتساب ومن أن Instance متصل.';
        }

        if (str_starts_with($message, 'Evolution API: ')) {
            return substr($message, strlen('Evolution API: '));
        }

        return $message;
    }

    private static function extractHost(string $message): ?string
    {
        if (preg_match('#https?://([^/\s]+)#', $message, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
