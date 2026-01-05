<?php

namespace App\Commands\Gmail;

use App\Services\Analytics;
use App\Services\MimeHelper;

/**
 * Searches Gmail threads using query syntax.
 *
 * Returns: thread ID, date, sender, subject, labels.
 */
class SearchCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:search
        {--query= : Search query}
        {--max=20 : Maximum results}
        {--page= : Page token for pagination}';

    protected $description = 'Search threads using Gmail query syntax';
    private MimeHelper $mime;
    private array $labelsMap = [];
    private array $results = [];

    public function handle(Analytics $analytics): int
    {
        $startTime = microtime(true);
        $email = null;
        $query = $this->option('query');
        $max = (int) $this->option('max');
        $pageToken = $this->option('page');

        if (empty($query)) {
            if ($this->shouldOutputJson()) {
                $analytics->track('gmail:search', self::FAILURE, ['result_count' => 0], $startTime);

                return $this->jsonError('Missing search query.');
            }
            $this->error('Missing search query.');
            $this->line('Usage: gmcli <email> search "<query>" [--max N] [--page TOKEN]');

            $analytics->track('gmail:search', self::FAILURE, ['result_count' => 0], $startTime);

            return self::FAILURE;
        }

        if (! $this->initGmail()) {
            $analytics->track('gmail:search', self::FAILURE, ['result_count' => 0], $startTime);

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
                if ($this->shouldOutputJson()) {
                    $analytics->track('gmail:search', self::SUCCESS, ['result_count' => 0], $startTime);

                    return $this->outputJson([]);
                }
                $this->info('No threads found.');

                $analytics->track('gmail:search', self::SUCCESS, ['result_count' => 0], $startTime);

                return self::SUCCESS;
            }

            // Fetch thread details for each result
            foreach ($threads as $thread) {
                $this->collectThread($thread['id']);
            }

            // JSON output
            if ($this->shouldOutputJson()) {
                $output = ['threads' => $this->results];
                if (isset($response['nextPageToken'])) {
                    $output['nextPageToken'] = $response['nextPageToken'];
                }

                $analytics->track('gmail:search', self::SUCCESS, ['result_count' => count($this->results)], $startTime);

                return $this->outputJson($output);
            }

            // Text output
            foreach ($this->results as $result) {
                $labels = $result['labels'] ? '['.implode(', ', $result['labels']).']' : '';
                $this->line("{$result['threadId']}\t{$result['date']}\t{$result['from']}\t{$result['subject']}\t{$labels}");
            }

            // Show pagination token if present
            if (isset($response['nextPageToken'])) {
                $this->newLine();
                $this->line("Next page: --page {$response['nextPageToken']}");
            }

            $analytics->track('gmail:search', self::SUCCESS, ['result_count' => count($this->results)], $startTime);

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $analytics->track('gmail:search', self::FAILURE, ['result_count' => 0], $startTime);

            if ($this->shouldOutputJson()) {
                return $this->jsonError($e->getMessage());
            }
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

    private function collectThread(string $threadId): void
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
        $labelNames = [];
        foreach ($labelIds as $id) {
            $labelNames[] = $this->labelsMap[$id] ?? $id;
        }

        $this->results[] = [
            'threadId' => $threadId,
            'date' => $date,
            'from' => $from,
            'subject' => $subject,
            'labels' => $labelNames,
        ];
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
}
