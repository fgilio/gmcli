<?php

namespace App\Commands\Gmail;

/**
 * Deletes a Gmail draft.
 */
class DraftsDeleteCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:delete
        {email}
        {--draft-id= : Draft ID to delete}';

    protected $description = 'Delete a draft';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');
        $draftId = $this->option('draft-id');

        if (empty($draftId)) {
            $this->error('Missing draft ID.');
            $this->line('Usage: gmcli <email> drafts delete <draftId>');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        try {
            $this->logger->verbose("Deleting draft: {$draftId}");

            // Gmail API uses DELETE method, but we'll use the drafts.delete endpoint
            // Need to add delete support to GmailClient
            $this->deleteDraft($draftId);

            $this->info("Draft deleted: {$draftId}");

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function deleteDraft(string $draftId): void
    {
        // Use raw curl for DELETE since GmailClient only has GET/POST
        $accessToken = $this->getAccessToken();

        $ch = curl_init("https://gmail.googleapis.com/gmail/v1/users/me/drafts/{$draftId}");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$accessToken}",
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            $error = json_decode($response, true);
            $message = $error['error']['message'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException("Delete failed: {$message}");
        }
    }

    private function getAccessToken(): string
    {
        // Trigger token refresh by making a simple request
        $this->gmail->get('/users/me/profile');

        // Get token via reflection (hacky but works)
        $reflection = new \ReflectionClass($this->gmail);
        $property = $reflection->getProperty('accessToken');
        $property->setAccessible(true);

        return $property->getValue($this->gmail);
    }
}
