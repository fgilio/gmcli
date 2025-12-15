<?php

namespace App\Commands\Gmail;

use App\Services\MimeHelper;

/**
 * Gets a Gmail draft with content.
 */
class DraftsGetCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:get
        {email}
        {--draft-id= : Draft ID}
        {--download : Download attachments}';

    protected $description = 'View draft with attachments';

    protected $hidden = true;

    private MimeHelper $mime;

    public function handle(): int
    {
        $email = $this->argument('email');
        $draftId = $this->option('draft-id');
        $download = $this->option('download');

        if (empty($draftId)) {
            $this->error('Missing draft ID.');
            $this->line('Usage: gmcli <email> drafts get <draftId> [--download]');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        $this->mime = new MimeHelper;

        try {
            $this->logger->verbose("Fetching draft: {$draftId}");

            $draft = $this->gmail->get("/users/me/drafts/{$draftId}", [
                'format' => 'full',
            ]);

            $message = $draft['message'] ?? [];
            $payload = $message['payload'] ?? [];

            // Headers
            $to = $this->mime->getHeader($payload, 'To') ?? '';
            $cc = $this->mime->getHeader($payload, 'Cc');
            $subject = $this->mime->getHeader($payload, 'Subject') ?? '(no subject)';

            $this->line("Draft-ID: {$draftId}");
            $this->line("Message-ID: " . ($message['id'] ?? ''));
            $this->line("To: {$to}");
            if ($cc) {
                $this->line("Cc: {$cc}");
            }
            $this->line("Subject: {$subject}");
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
                        $this->downloadAttachment($message['id'], $att);
                    }
                }
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
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

        $filename = $this->buildSafeFilename($messageId, $attachment);
        $path = $this->getAttachmentsPath() . '/' . $filename;

        file_put_contents($path, $content);
        $this->info("    Saved: {$path}");
    }

    private function buildSafeFilename(string $messageId, array $attachment): string
    {
        $attachmentIdPrefix = substr($attachment['attachmentId'], 0, 8);
        $name = $this->sanitizeFilename($attachment['filename']);

        return "{$messageId}_{$attachmentIdPrefix}_{$name}";
    }

    private function sanitizeFilename(string $filename): string
    {
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
