<?php

namespace App\Commands\Gmail;

use App\Services\MessageBuilder;
use App\Services\MimeHelper;

/**
 * Creates a new Gmail draft.
 */
class DraftsCreateCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:create
        {email}
        {--to= : Recipients (comma-separated)}
        {--subject= : Subject line}
        {--body= : Message body}
        {--cc= : CC recipients (comma-separated)}
        {--bcc= : BCC recipients (comma-separated)}
        {--reply-to= : Message ID to reply to}
        {--attach=* : File attachments}';

    protected $description = 'Create a new draft';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');
        $to = $this->option('to');
        $subject = $this->option('subject');
        $body = $this->option('body');
        $cc = $this->option('cc');
        $bcc = $this->option('bcc');
        $replyTo = $this->option('reply-to');
        $attachments = $this->option('attach') ?: [];

        if (empty($to) || empty($subject) || empty($body)) {
            $this->error('Missing required options.');
            $this->line('Usage: gmcli <email> drafts create --to <emails> --subject <s> --body <b>');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        try {
            $builder = new MessageBuilder;
            $builder->from($this->env->getEmail())
                ->to($this->parseEmails($to))
                ->subject($subject)
                ->body($body);

            if ($cc) {
                $builder->cc($this->parseEmails($cc));
            }

            if ($bcc) {
                $builder->bcc($this->parseEmails($bcc));
            }

            // Handle reply-to
            if ($replyTo) {
                $this->setupReplyTo($builder, $replyTo);
            }

            // Add attachments
            foreach ($attachments as $path) {
                $builder->attach($path);
            }

            $raw = $builder->build();
            $payload = ['message' => ['raw' => $raw]];

            // Add thread ID if replying
            if ($builder->getThreadId()) {
                $payload['message']['threadId'] = $builder->getThreadId();
            }

            $this->logger->verbose('Creating draft...');
            $response = $this->gmail->post('/users/me/drafts', $payload);

            $draftId = $response['id'] ?? '';
            $this->info("Draft created: {$draftId}");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function parseEmails(string $emails): array
    {
        return array_map('trim', explode(',', $emails));
    }

    private function setupReplyTo(MessageBuilder $builder, string $messageId): void
    {
        $this->logger->verbose("Fetching reply-to message: {$messageId}");

        // Get message metadata for threading headers
        $message = $this->gmail->get("/users/me/messages/{$messageId}", [
            'format' => 'metadata',
            'metadataHeaders' => ['Message-ID', 'References'],
        ]);

        $mime = new MimeHelper;
        $payload = $message['payload'] ?? [];

        $headerMsgId = $mime->getHeader($payload, 'Message-ID') ?? '';
        $references = $mime->getHeader($payload, 'References') ?? '';

        // Build references chain
        $newReferences = $references ? "{$references} {$headerMsgId}" : $headerMsgId;

        $builder->replyTo($headerMsgId, $newReferences, $message['threadId'] ?? null);
    }
}
