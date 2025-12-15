<?php

namespace App\Commands\Gmail;

use App\Services\LabelResolver;

/**
 * Modifies labels on Gmail threads.
 */
class LabelsModifyCommand extends BaseGmailCommand
{
    protected $signature = 'gmail:labels:modify
        {email}
        {--thread-ids=* : Thread IDs to modify}
        {--add=* : Labels to add}
        {--remove=* : Labels to remove}';

    protected $description = 'Modify labels on threads';

    protected $hidden = true;

    public function handle(): int
    {
        $email = $this->argument('email');
        $threadIds = $this->option('thread-ids') ?: [];
        $addLabels = $this->option('add') ?: [];
        $removeLabels = $this->option('remove') ?: [];

        if (empty($threadIds)) {
            $this->error('Missing thread IDs.');
            $this->line('Usage: gmcli <email> labels <threadIds...> [--add L] [--remove L]');

            return self::FAILURE;
        }

        if (empty($addLabels) && empty($removeLabels)) {
            $this->error('No labels to add or remove.');
            $this->line('Usage: gmcli <email> labels <threadIds...> [--add L] [--remove L]');

            return self::FAILURE;
        }

        if (! $this->initGmail($email)) {
            return self::FAILURE;
        }

        try {
            // Load and resolve labels
            $resolver = $this->loadLabels();

            $addResolved = $resolver->resolveMany($addLabels);
            $removeResolved = $resolver->resolveMany($removeLabels);

            // Report any not found labels
            if (! empty($addResolved['notFound'])) {
                $this->warn('Labels not found (add): ' . implode(', ', $addResolved['notFound']));
            }
            if (! empty($removeResolved['notFound'])) {
                $this->warn('Labels not found (remove): ' . implode(', ', $removeResolved['notFound']));
            }

            // Nothing to do if all labels not found
            if (empty($addResolved['resolved']) && empty($removeResolved['resolved'])) {
                $this->error('No valid labels to modify.');

                return self::FAILURE;
            }

            // Modify each thread
            foreach ($threadIds as $threadId) {
                $this->modifyThread($threadId, $addResolved['resolved'], $removeResolved['resolved']);
            }

            $this->info('Labels modified.');

            return self::SUCCESS;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function loadLabels(): LabelResolver
    {
        $this->logger->verbose('Loading labels...');

        $response = $this->gmail->get('/users/me/labels');
        $labels = $response['labels'] ?? [];

        $resolver = new LabelResolver;
        $resolver->load($labels);

        return $resolver;
    }

    private function modifyThread(string $threadId, array $addLabelIds, array $removeLabelIds): void
    {
        $this->logger->verbose("Modifying thread: {$threadId}");

        $body = [];

        if (! empty($addLabelIds)) {
            $body['addLabelIds'] = $addLabelIds;
        }

        if (! empty($removeLabelIds)) {
            $body['removeLabelIds'] = $removeLabelIds;
        }

        $this->gmail->post("/users/me/threads/{$threadId}/modify", $body);
    }
}
