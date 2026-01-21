<?php

namespace App\Services;

/**
 * Parses Gmail thread IDs from various formats.
 *
 * Supports:
 * - Hex thread IDs (API format): 19aea1f2f3532db5
 * - FMfcg view tokens (web URL format): FMfcgzQdzmSkKNRjSnNvmnxrjlNJTKlg
 * - Full Gmail URLs: https://mail.google.com/mail/u/1/#inbox/FMfcg...
 */
class GmailIdHelper
{
    // Vowel-free charset used in FMfcg tokens
    private const CHARSET_REDUCED = 'BCDFGHJKLMNPQRSTVWXZbcdfghjklmnpqrstvwxz';

    /**
     * Parses input (URL or ID) and returns hex thread ID.
     *
     * @return array{threadId: string, source: string, original: string}
     */
    public function parse(string $input): array
    {
        $input = trim($input);
        $original = $input;

        // Extract ID from URL if needed
        $id = $this->extractFromUrl($input) ?? $input;

        // Detect format and convert
        if ($this->isHexId($id)) {
            return [
                'threadId' => strtolower($id),
                'source' => 'hex',
                'original' => $original,
            ];
        }

        if ($this->isFMfcgToken($id)) {
            try {
                $hexId = $this->decodeFMfcg($id);

                return [
                    'threadId' => $hexId,
                    'source' => 'fmfcg',
                    'original' => $original,
                ];
            } catch (\InvalidArgumentException $e) {
                // Decoding failed, return as-is
            }
        }

        // Unknown format - return as-is, let API decide
        return [
            'threadId' => $id,
            'source' => 'unknown',
            'original' => $original,
        ];
    }

    /**
     * Extracts thread ID from Gmail URL.
     */
    public function extractFromUrl(string $input): ?string
    {
        if (! str_contains($input, 'mail.google.com')) {
            return null;
        }

        // Pattern: #inbox/ID, #all/ID, #sent/ID, #label/name/ID, etc.
        if (preg_match('/#[^\/]+\/([A-Za-z0-9]+)$/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Checks if ID looks like hex thread ID.
     */
    public function isHexId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-fA-F]{12,20}$/', $id);
    }

    /**
     * Checks if ID looks like FMfcg view token.
     */
    public function isFMfcgToken(string $id): bool
    {
        return str_starts_with($id, 'FMfcg');
    }

    /**
     * Decodes FMfcg view token to hex thread ID.
     *
     * Algorithm based on Arsenal Recon research:
     * https://arsenalrecon.com/insights/digging-deeper-into-gmail-urls-and-introducing-gmail-url-decoder
     */
    public function decodeFMfcg(string $token): string
    {
        // Transform full token from reduced charset to standard base64
        $base64 = $this->transformCharset($token);

        // Add padding and decode
        $padding = str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode($base64.$padding, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException("Failed to base64 decode: {$token}");
        }

        // The decoded string may or may not have "thread-" prefix
        // Extract decimal ID from "thread-f:123456789" or "thread-a:-123456789" format
        // Also handle cases without "thread-" prefix (just "f:123..." or raw number)
        if (preg_match('/(?:thread-)?[af]:r?(-?\d+)/', $decoded, $matches)) {
            $decimal = $matches[1];
            // Handle negative numbers (thread-a format)
            if ($decimal[0] === '-') {
                $decimal = substr($decimal, 1);
            }

            return dechex((int) $decimal);
        }

        throw new \InvalidArgumentException("Unexpected decoded format: {$decoded}");
    }

    /**
     * Transforms string from reduced (vowel-free) charset to standard base64.
     *
     * Algorithm from Arsenal Recon's GmailURLDecoder.
     * https://github.com/ArsenalRecon/GmailURLDecoder
     */
    private function transformCharset(string $input): string
    {
        $base64Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
        $sizeIn = strlen(self::CHARSET_REDUCED);
        $sizeOut = strlen($base64Chars);

        // Build alphabet map
        $alphMap = array_flip(str_split(self::CHARSET_REDUCED));

        // Read input backwards, collecting indices
        $inStrIdx = [];
        for ($i = strlen($input) - 1; $i >= 0; $i--) {
            $inStrIdx[] = $alphMap[$input[$i]] ?? 0;
        }

        // Base conversion with carry propagation
        $outStrIdx = [];
        for ($i = count($inStrIdx) - 1; $i >= 0; $i--) {
            $offset = 0;

            for ($j = 0; $j < count($outStrIdx); $j++) {
                $idx = $sizeIn * $outStrIdx[$j] + $offset;

                if ($idx >= $sizeOut) {
                    $rest = $idx % $sizeOut;
                    $offset = intdiv($idx - $rest, $sizeOut);
                    $idx = $rest;
                } else {
                    $offset = 0;
                }

                $outStrIdx[$j] = $idx;
            }

            while ($offset) {
                $rest = $offset % $sizeOut;
                $outStrIdx[] = $rest;
                $offset = intdiv($offset - $rest, $sizeOut);
            }

            $offset = $inStrIdx[$i];
            $j = 0;

            while ($offset) {
                if ($j >= count($outStrIdx)) {
                    $outStrIdx[] = 0;
                }

                $idx = $outStrIdx[$j] + $offset;

                if ($idx >= $sizeOut) {
                    $rest = $idx % $sizeOut;
                    $offset = intdiv($idx - $rest, $sizeOut);
                    $idx = $rest;
                } else {
                    $offset = 0;
                }

                $outStrIdx[$j] = $idx;
                $j++;
            }
        }

        // Build output string in reverse
        $result = '';
        for ($i = count($outStrIdx) - 1; $i >= 0; $i--) {
            $result .= $base64Chars[$outStrIdx[$i]];
        }

        return $result;
    }
}
