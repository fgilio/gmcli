<?php

namespace App\Commands\Gmail;

use App\Services\GmailClient;
use App\Services\GmailLogger;
use App\Services\GmcliEnv;
use LaravelZero\Framework\Commands\Command;

/**
 * Base class for Gmail commands.
 *
 * Provides common functionality:
 * - Email validation against configured account
 * - Gmail client creation with logging
 * - Verbose/debug output support
 */
abstract class BaseGmailCommand extends Command
{
    protected GmcliEnv $env;
    protected GmailClient $gmail;
    protected GmailLogger $logger;

    /**
     * Initializes Gmail client and validates email.
     *
     * @return bool True if initialization succeeded
     */
    protected function initGmail(string $email): bool
    {
        $this->env = app(GmcliEnv::class);
        $this->logger = new GmailLogger(
            $this->output,
            $this->output->isVerbose(),
            $this->output->isVeryVerbose()
        );

        if (! $this->env->hasCredentials()) {
            $this->error('No credentials configured.');
            $this->line('Run: gmcli accounts credentials <file.json>');

            return false;
        }

        if (! $this->env->hasAccount()) {
            $this->error('No account configured.');
            $this->line('Run: gmcli accounts add <email>');

            return false;
        }

        if (! $this->env->matchesEmail($email)) {
            $configuredEmail = $this->env->getEmail();
            $this->error("Email does not match configured account: {$configuredEmail}");

            return false;
        }

        $this->gmail = new GmailClient(
            $this->env->get('GOOGLE_CLIENT_ID'),
            $this->env->get('GOOGLE_CLIENT_SECRET'),
            $this->env->get('GMAIL_REFRESH_TOKEN')
        );
        $this->gmail->setLogger($this->logger);

        // Check for permission warnings
        $warning = $this->env->getPermissionWarning();
        if ($warning) {
            $this->warn($warning);
            $this->newLine();
        }

        return true;
    }
}
