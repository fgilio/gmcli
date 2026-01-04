<?php

namespace App\Commands\Gmail;

use App\Services\Analytics;

/**
 * Lists all Gmail drafts.
 */
class DraftsListCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:drafts:list {email}';

    protected $description = 'List all drafts';

    protected $hidden = true;

    public function handle(Analytics $analytics): int
    {
        $startTime = microtime(true);
        $email = $this->argument('email');

        if (! $this->initGmail($email)) {
            $analytics->track('gmail:drafts:list', self::FAILURE, ['count' => 0], $startTime);

            return self::FAILURE;
        }

        try {
            $this->logger->verbose('Fetching drafts...');

            $response = $this->gmail->get('/users/me/drafts');
            $drafts = $response['drafts'] ?? [];

            if (empty($drafts)) {
                if ($this->shouldOutputJson()) {
                    $analytics->track('gmail:drafts:list', self::SUCCESS, ['count' => 0], $startTime);

                    return $this->outputJson([]);
                }
                $this->info('No drafts found.');

                $analytics->track('gmail:drafts:list', self::SUCCESS, ['count' => 0], $startTime);

                return self::SUCCESS;
            }

            $results = array_map(fn($d) => [
                'draftId' => $d['id'],
                'messageId' => $d['message']['id'] ?? '',
            ], $drafts);

            if ($this->shouldOutputJson()) {
                $analytics->track('gmail:drafts:list', self::SUCCESS, ['count' => count($results)], $startTime);

                return $this->outputJson($results);
            }

            foreach ($results as $result) {
                $this->line("{$result['draftId']}\t{$result['messageId']}");
            }

            $analytics->track('gmail:drafts:list', self::SUCCESS, ['count' => count($results)], $startTime);

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $analytics->track('gmail:drafts:list', self::FAILURE, ['count' => 0], $startTime);

            return $this->jsonError($e->getMessage());
        }
    }
}
