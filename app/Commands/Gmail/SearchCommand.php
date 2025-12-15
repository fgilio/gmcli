<?php

namespace App\Commands\Gmail;

use App\Services\MimeHelper;

/**
 * Searches Gmail threads using query syntax.
 *
 * Returns: thread ID, date, sender, subject, labels.
 */
class SearchCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:search
        {email}
        {--query= : Search query}
        {--max=20 : Maximum results}
        {--page= : Page token for pagination}';

    protected $description = 'Search threads using Gmail query syntax';

    protected $hidden = true;

    private MimeHelper $mime;
    private array $labelsMap = [];

    public function handle(): int
    {
        $email = $this->argument('email');
        $query = $this->option('query');
        $max = (int) $this->option('max');
        $pageToken = $this->option('page');

        if (empty($query)) {
            $this->error('Missing search query.');
            $this->line('Usage: gmcli <email> search "<query>" [--max N] [--page TOKEN]');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        $this->mime = new MimeHelper;

        try {
            // Load labels map for display
            $this->loadLabelsMap();

            // Search threads
            $params = [
                'q' => $query,
                'maxResults' => min($max, 500),
            ];

            if ($pageToken) {
                $params['pageToken'] = $pageToken;
            }

            $this->logger->verbose("Searching: {$query}");

            $response = $this->gmail->get('/users/me/threads', $params);
            $threads = $response['threads'] ?? [];

            if (empty($threads)) {
                $this->info('No threads found.');

                return self::SUCCESS;
            }

            // Fetch thread details for each result
            foreach ($threads as $thread) {
                $this->displayThread($thread['id']);
            }

            // Show pagination token if present
            if (isset($response['nextPageToken'])) {
                $this->newLine();
                $this->line("Next page: --page {$response['nextPageToken']}");
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function loadLabelsMap(): void
    {
        $response = $this->gmail->get('/users/me/labels');
        $labels = $response['labels'] ?? [];

        foreach ($labels as $label) {
            $this->labelsMap[$label['id']] = $label['name'];
        }
    }

    private function displayThread(string $threadId): void
    {
        $thread = $this->gmail->get("/users/me/threads/{$threadId}", [
            'format' => 'metadata',
            'metadataHeaders' => ['From', 'Subject', 'Date'],
        ]);

        $messages = $thread['messages'] ?? [];
        if (empty($messages)) {
            return;
        }

        // Get first message for metadata
        $firstMessage = $messages[0];
        $payload = $firstMessage['payload'] ?? [];

        $date = $this->mime->getHeader($payload, 'Date') ?? '';
        $from = $this->mime->getHeader($payload, 'From') ?? '';
        $subject = $this->mime->getHeader($payload, 'Subject') ?? '(no subject)';

        // Format date
        $date = $this->formatDate($date);

        // Format sender (just email or name)
        $from = $this->formatSender($from);

        // Get labels
        $labelIds = $firstMessage['labelIds'] ?? [];
        $labels = $this->formatLabels($labelIds);

        // Output: threadId  date  from  subject  [labels]
        $this->line("{$threadId}\t{$date}\t{$from}\t{$subject}\t{$labels}");
    }

    private function formatDate(string $date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dt = new \DateTime($date);

            return $dt->format('Y-m-d H:i');
        } catch (\Exception) {
            return substr($date, 0, 16);
        }
    }

    private function formatSender(string $from): string
    {
        // Extract just the email address or name
        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return $matches[1];
        }

        return $from;
    }

    private function formatLabels(array $labelIds): string
    {
        if (empty($labelIds)) {
            return '';
        }

        $names = [];
        foreach ($labelIds as $id) {
            $names[] = $this->labelsMap[$id] ?? $id;
        }

        return '[' . implode(', ', $names) . ']';
    }
}
