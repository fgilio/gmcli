<?php

namespace App\Services;

/**
 * Handles MIME message parsing and traversal.
 *
 * Extracts text/plain content from Gmail message payloads,
 * supporting nested multipart structures.
 */
class MimeHelper
{
    /**
     * Extracts text/plain body from message payload.
     */
    public function extractTextBody(array $payload): string
    {
        // Direct text/plain body
        if (($payload['mimeType'] ?? '') === 'text/plain' && isset($payload['body']['data'])) {
            return $this->decodeBase64Url($payload['body']['data']);
        }

        // Search in parts
        if (isset($payload['parts'])) {
            return $this->extractFromParts($payload['parts']);
        }

        return '';
    }

    /**
     * Extracts text/plain content from message parts.
     */
    private function extractFromParts(array $parts): string
    {
        foreach ($parts as $part) {
            $mimeType = $part['mimeType'] ?? '';

            // Direct text/plain part
            if ($mimeType === 'text/plain' && isset($part['body']['data'])) {
                return $this->decodeBase64Url($part['body']['data']);
            }

            // Recurse into multipart
            if (str_starts_with($mimeType, 'multipart/') && isset($part['parts'])) {
                $text = $this->extractFromParts($part['parts']);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * Gets attachment metadata from message payload.
     *
     * @return array<array{filename: string, mimeType: string, size: int, attachmentId: string}>
     */
    public function getAttachments(array $payload): array
    {
        $attachments = [];
        $this->collectAttachments($payload, $attachments);

        return $attachments;
    }

    private function collectAttachments(array $payload, array &$attachments): void
    {
        // Check if this part is an attachment
        if (isset($payload['filename']) && $payload['filename'] !== '') {
            $attachments[] = [
                'filename' => $payload['filename'],
                'mimeType' => $payload['mimeType'] ?? 'application/octet-stream',
                'size' => $payload['body']['size'] ?? 0,
                'attachmentId' => $payload['body']['attachmentId'] ?? '',
            ];
        }

        // Recurse into parts
        if (isset($payload['parts'])) {
            foreach ($payload['parts'] as $part) {
                $this->collectAttachments($part, $attachments);
            }
        }
    }

    /**
     * Gets header value from message payload.
     */
    public function getHeader(array $payload, string $name): ?string
    {
        $headers = $payload['headers'] ?? [];

        foreach ($headers as $header) {
            if (strcasecmp($header['name'], $name) === 0) {
                return $header['value'];
            }
        }

        return null;
    }

    /**
     * Decodes base64url-encoded string.
     */
    public function decodeBase64Url(string $data): string
    {
        // Replace URL-safe characters
        $data = strtr($data, '-_', '+/');

        // Add padding if needed
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($data) ?: '';
    }

    /**
     * Encodes string to base64url.
     */
    public function encodeBase64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
