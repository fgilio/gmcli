<?php

namespace App\Commands\Gmail;

/**
 * Lists all Gmail labels.
 */
class LabelsListCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:labels:list {email}';

    protected $description = 'List all Gmail labels';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        $this->logger->verbose('Fetching labels...');

        try {
            $response = $this->gmail->get('/users/me/labels');
            $labels = $response['labels'] ?? [];

            // Sort by name
            usort($labels, fn($a, $b) => strcasecmp($a['name'], $b['name']));

            foreach ($labels as $label) {
                $this->formatLabel($label);
            }

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function formatLabel(array $label): void
    {
        $id = $label['id'];
        $name = $label['name'];
        $type = $label['type'] ?? 'user';

        $typeTag = $type === 'system' ? ' (system)' : '';

        $this->line("{$id}\t{$name}{$typeTag}");
    }
}
