<?php

namespace App\Commands\Gmail;

use App\Services\Analytics;

/**
 * Lists all Gmail labels.
 */
class LabelsListCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:labels:list ';

    protected $description = 'List all Gmail labels';
    public function handle(Analytics $analytics): int
    {
        $startTime = microtime(true);
        $email = null;

        if (! $this->initGmail()) {
            $analytics->track('gmail:labels:list', self::FAILURE, ['count' => 0], $startTime);

            return self::FAILURE;
        }

        $this->logger->verbose('Fetching labels...');

        try {
            $response = $this->gmail->get('/users/me/labels');
            $labels = $response['labels'] ?? [];

            // Sort by name
            usort($labels, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            // JSON output
            if ($this->shouldOutputJson()) {
                $jsonLabels = array_map(fn($l) => [
                    'id' => $l['id'],
                    'name' => $l['name'],
                    'type' => $l['type'] ?? 'user',
                ], $labels);

                $analytics->track('gmail:labels:list', self::SUCCESS, ['count' => count($labels)], $startTime);

                return $this->outputJson($jsonLabels);
            }

            // Text output
            foreach ($labels as $label) {
                $type = $label['type'] ?? 'user';
                $typeTag = $type === 'system' ? ' (system)' : '';
                $this->line("{$label['id']}\t{$label['name']}{$typeTag}");
            }

            $analytics->track('gmail:labels:list', self::SUCCESS, ['count' => count($labels)], $startTime);

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $analytics->track('gmail:labels:list', self::FAILURE, ['count' => 0], $startTime);

            return $this->jsonError($e->getMessage());
        }
    }
}
