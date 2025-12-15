<?php

namespace App\Commands\Gmail;

use App\Services\MimeHelper;

/**
 * Displays a Gmail thread with all messages.
 *
 * Shows: Message-ID, headers, body, attachments.
 */
class ThreadCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:thread
        {email}
        {--thread-id= : Thread ID to view}
        {--download : Download attachments}';

    protected $description = 'Get thread with all messages';

    protected $hidden = true;

    private MimeHelper $mime;

    public function handle(): int
    {
        $email = $this->argument('email');
        $threadId = $this->option('thread-id');
        $download = $this->option('download');

        if (empty($threadId)) {
            $this->error('Missing thread ID.');
            $this->line('Usage: gmcli <email> thread <threadId> [--download]');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        $this->mime = new MimeHelper;

        try {
            $this->logger->verbose("Fetching thread: {$threadId}");

            $thread = $this->gmail->get("/users/me/threads/{$threadId}", [
                'format' => 'full',
            ]);

            $messages = $thread['messages'] ?? [];

            if (empty($messages)) {
                $this->info('Thread has no messages.');

                return self::SUCCESS;
            }

            foreach ($messages as $index => $message) {
                if ($index > 0) {
                    $this->line(str_repeat('-', 60));
                }

                $this->displayMessage($message, $download);
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function displayMessage(array $message, bool $download): void
    {
        $payload = $message['payload'] ?? [];
        $messageId = $message['id'];

        // Headers
        $from = $this->mime->getHeader($payload, 'From') ?? '';
        $to = $this->mime->getHeader($payload, 'To') ?? '';
        $cc = $this->mime->getHeader($payload, 'Cc');
        $date = $this->mime->getHeader($payload, 'Date') ?? '';
        $subject = $this->mime->getHeader($payload, 'Subject') ?? '(no subject)';
        $msgIdHeader = $this->mime->getHeader($payload, 'Message-ID') ?? '';

        $this->line("Message-ID: {$messageId}");
        if ($msgIdHeader) {
            $this->line("Header-ID: {$msgIdHeader}");
        }
        $this->line("Date: {$date}");
        $this->line("From: {$from}");
        $this->line("To: {$to}");
        if ($cc) {
            $this->line("Cc: {$cc}");
        }
        $this->line("Subject: {$subject}");

        // Labels
        $labelIds = $message['labelIds'] ?? [];
        if (! empty($labelIds)) {
            $this->line("Labels: " . implode(', ', $labelIds));
        }

        $this->newLine();

        // Body
        $body = $this->mime->extractTextBody($payload);
        if ($body !== '') {
            $this->line($body);
        } else {
            $this->line('(no text/plain body)');
        }

        // Attachments
        $attachments = $this->mime->getAttachments($payload);
        if (! empty($attachments)) {
            $this->newLine();
            $this->line("Attachments:");

            foreach ($attachments as $att) {
                $size = $this->formatSize($att['size']);
                $this->line("  - {$att['filename']} ({$att['mimeType']}, {$size})");

                if ($download && $att['attachmentId']) {
                    $this->downloadAttachment($messageId, $att);
                }
            }
        }
    }

    private function downloadAttachment(string $messageId, array $attachment): void
    {
        $this->logger->verbose("Downloading: {$attachment['filename']}");

        $response = $this->gmail->get(
            "/users/me/messages/{$messageId}/attachments/{$attachment['attachmentId']}"
        );

        $data = $response['data'] ?? '';
        if (empty($data)) {
            $this->warn("    Failed to download: empty data");

            return;
        }

        $content = $this->mime->decodeBase64Url($data);

        // Build safe filename
        $filename = $this->buildSafeFilename($messageId, $attachment);
        $path = $this->getAttachmentsPath() . '/' . $filename;

        file_put_contents($path, $content);
        $this->info("    Saved: {$path}");
    }

    private function buildSafeFilename(string $messageId, array $attachment): string
    {
        // Format: {messageId}_{attachmentId8}_{name}
        $attachmentIdPrefix = substr($attachment['attachmentId'], 0, 8);
        $name = $this->sanitizeFilename($attachment['filename']);

        return "{$messageId}_{$attachmentIdPrefix}_{$name}";
    }

    private function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts and invalid characters
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename ?: 'attachment';
    }

    private function getAttachmentsPath(): string
    {
        $paths = app(\App\Services\GmcliPaths::class);
        $paths->ensureAttachmentsDir();

        return $paths->attachmentsDir();
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
