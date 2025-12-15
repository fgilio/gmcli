<?php

namespace App\Commands\Gmail;

/**
 * Generates Gmail web URLs for threads.
 *
 * Uses canonical URL format with email parameter.
 */
class UrlCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:url
        {email}
        {--thread-ids=* : Thread IDs}';

    protected $description = 'Generate Gmail web URLs for threads';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');
        $threadIds = $this->option('thread-ids') ?: [];

        if (empty($threadIds)) {
            $this->error('Missing thread IDs.');
            $this->line('Usage: gmcli <email> url <threadIds...>');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        $configuredEmail = $this->env->getEmail();

        foreach ($threadIds as $threadId) {
            $url = $this->buildGmailUrl($threadId, $configuredEmail);
            $this->line("{$threadId}\t{$url}");
        }

        return self::SUCCESS;
    }

    /**
     * Builds canonical Gmail web URL for a thread.
     */
    public function buildGmailUrl(string $threadId, string $email): string
    {
        // Convert thread ID from hex to decimal
        // Gmail uses decimal thread IDs in URLs
        $decimal = $this->hexToDecimal($threadId);

        // URL encode the email
        $encodedEmail = urlencode($email);

        return "https://mail.google.com/mail/u/?authuser={$encodedEmail}#all/{$decimal}";
    }

    /**
     * Converts hex string to decimal string.
     *
     * Gmail thread IDs are hex, but URLs use decimal format.
     */
    private function hexToDecimal(string $hex): string
    {
        // Remove any leading zeros or 0x prefix
        $hex = ltrim($hex, '0x');

        // For short hex values, use native conversion
        if (strlen($hex) <= 15) {
            return (string) hexdec($hex);
        }

        // For longer hex values, use bcmath if available
        if (function_exists('bcadd')) {
            $decimal = '0';
            $len = strlen($hex);
            for ($i = 0; $i < $len; $i++) {
                $digit = hexdec($hex[$i]);
                $decimal = bcmul($decimal, '16');
                $decimal = bcadd($decimal, (string) $digit);
            }

            return $decimal;
        }

        // Fallback: use hex directly (some URLs support this)
        return $hex;
    }
}
