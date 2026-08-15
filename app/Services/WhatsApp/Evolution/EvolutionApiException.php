<?php

namespace App\Services\WhatsApp\Evolution;

use Illuminate\Http\Client\ConnectionException;
use Throwable;

class EvolutionApiException extends \RuntimeException
{
    public function __construct(
        private readonly string $userMessage,
        ?string $technicalMessage = null,
        int $httpStatus = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($technicalMessage ?? $userMessage, $httpStatus, $previous);
    }

    public function userMessage(): string
    {
        return $this->userMessage;
    }

    public function httpStatus(): int
    {
        return (int) $this->getCode();
    }

    public static function isNotFound(Throwable $e): bool
    {
        if ($e instanceof self && $e->httpStatus() === 404) {
            return true;
        }

        $message = strtolower($e->getMessage());

        return str_contains($message, 'not found')
            || str_contains($message, '404');
    }

    public static function resolveUserMessage(Throwable $e): string
    {
        if ($e instanceof self) {
            return $e->userMessage();
        }

        if ($e instanceof ConnectionException) {
            return 'تعذر الاتصال بخادم Evolution API. تأكد أن الخدمة تعمل وأن عنوان Base URL في الإعدادات صحيح.';
        }

        $raw = $e->getMessage();
        if (self::looksLikeHtml($raw)) {
            return 'تعذر الاتصال بـ Evolution API. راجع إعدادات الاتصال وحاول مرة أخرى.';
        }

        $message = trim(strip_tags($raw));
        if ($message === '' || strlen($message) > 300) {
            return 'تعذر الاتصال بـ Evolution API. راجع إعدادات الاتصال وحاول مرة أخرى.';
        }

        return $message;
    }

    public static function looksLikeHtml(string $body): bool
    {
        $lower = strtolower($body);

        return str_contains($lower, '<!doctype')
            || str_contains($lower, '<html')
            || str_contains($lower, '<h1>')
            || str_contains($lower, 'litespeed');
    }
}
