<?php

namespace App\Commands\Gmail;

/**
 * Sends a Gmail draft.
 */
class DraftsSendCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:send
        {email}
        {--draft-id= : Draft ID to send}';

    protected $description = 'Send a draft';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');
        $draftId = $this->option('draft-id');

        if (empty($draftId)) {
            $this->error('Missing draft ID.');
            $this->line('Usage: gmcli <email> drafts send <draftId>');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        try {
            $this->logger->verbose("Sending draft: {$draftId}");

            $response = $this->gmail->post('/users/me/drafts/send', [
                'id' => $draftId,
            ]);

            $messageId = $response['id'] ?? '';
            $threadId = $response['threadId'] ?? '';

            $this->info("Draft sent successfully.");
            $this->line("Message-ID: {$messageId}");
            $this->line("Thread-ID: {$threadId}");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
