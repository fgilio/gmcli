<?php

namespace App\Commands\Gmail;

/**
 * Lists all Gmail drafts.
 */
class DraftsListCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:list {email}';

    protected $description = 'List all drafts';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        try {
            $this->logger->verbose('Fetching drafts...');

            $response = $this->gmail->get('/users/me/drafts');
            $drafts = $response['drafts'] ?? [];

            if (empty($drafts)) {
                $this->info('No drafts found.');

                return self::SUCCESS;
            }

            foreach ($drafts as $draft) {
                $draftId = $draft['id'];
                $messageId = $draft['message']['id'] ?? '';

                $this->line("{$draftId}\t{$messageId}");
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
