<?php

namespace App\Services\WhatsApp;

use App\DTOs\WhatsApp\SendMessageResponseDTO;

/**
 * Interface for WhatsApp providers
 */
interface WhatsAppProviderService
{
    /**
     * Send text message
     *
     * @param  string  $to  Phone number
     * @param  string  $text  Message content
     * @param  bool  $previewUrl  Enable URL preview
     */
    public function sendText(string $to, string $text, bool $previewUrl = false): SendMessageResponseDTO;

    /**
     * Send template message
     *
     * @param  string  $to  Phone number
     * @param  string  $templateName  Template name
     * @param  string  $language  Language code
     * @param  array  $components  Template components
     */
    public function sendTemplate(string $to, string $templateName, string $language = 'ar', array $components = []): SendMessageResponseDTO;

    /**
     * Send document (PDF, etc.)
     *
     * @param  string  $to  Phone number
     * @param  string  $documentUrl  URL of the document
     * @param  string  $filename  Document filename
     * @param  string|null  $caption  Optional caption for the document
     */
    public function sendDocument(string $to, string $documentUrl, string $filename, ?string $caption = null): SendMessageResponseDTO;

    /**
     * Test connection to WhatsApp provider
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array;

    /**
     * Whether the credentials needed to send are present.
     *
     * A cheap, offline check — no network call. Callers use it to avoid queueing work
     * that can only fail, and to explain in the UI why a channel is unavailable.
     */
    public function isConfigured(): bool;
}
