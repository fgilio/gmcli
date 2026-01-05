<?php

namespace App\Commands\Gmail;

use App\Services\GmailClient;
use App\Services\GmailLogger;
use App\Services\GmcliEnv;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Input\InputOption;

/**
 * Base class for Gmail commands.
 *
 * Provides common functionality:
 * - Account resolution (from --account option or default)
 * - Gmail client creation with logging
 * - Verbose/debug output support
 * - JSON output support via --json flag
 */
abstract class BaseGmailCommand extends Command
{
    protected GmcliEnv $env;
    protected GmailClient $gmail;
    protected GmailLogger $logger;
    protected string $account;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Output as JSON');
        $this->addOption('account', 'a', InputOption::VALUE_REQUIRED, 'Account email (uses default if not specified)');
    }

    /**
     * Resolves account from --account option or default.
     */
    protected function resolveAccount(): ?string
    {
        $this->env = app(GmcliEnv::class);

        // Use --account option if provided
        $account = $this->option('account');
        if ($account) {
            return $account;
        }

        // Fall back to configured default
        return $this->env->getEmail();
    }

    /**
     * Initializes Gmail client for the resolved account.
     *
     * @return bool True if initialization succeeded
     */
    protected function initGmail(?string $email = null): bool
    {
        $this->env = app(GmcliEnv::class);
        $this->logger = new GmailLogger(
            $this->output,
            $this->output->isVerbose(),
            $this->output->isVeryVerbose()
        );

        // Resolve account if not provided
        $email = $email ?? $this->resolveAccount();

        if (! $email) {
            $this->error('No account specified and no default configured.');
            $this->line('Either use: gmcli gmail:search --account you@gmail.com "query"');
            $this->line('Or add an account: gmcli accounts:add you@gmail.com');

            return false;
        }

        $this->account = $email;

        if (! $this->env->hasCredentials()) {
            $this->error('No credentials configured.');
            $this->line('Run: gmcli accounts:credentials <file.json>');

            return false;
        }

        if (! $this->env->hasAccount()) {
            $this->error('No account configured.');
            $this->line('Run: gmcli accounts:add <email>');

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

    /**
     * Checks if output should be JSON (explicit --json flag).
     */
    protected function shouldOutputJson(): bool
    {
        return $this->hasOption('json') && $this->option('json');
    }

    /**
     * Outputs data in standard JSON envelope.
     */
    protected function outputJson(mixed $data): int
    {
        $this->line(json_encode(['data' => $data], JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }

    /**
     * Outputs error as JSON to stderr.
     */
    protected function jsonError(string $message): int
    {
        fwrite(STDERR, json_encode(['error' => $message]) . "\n");

        return self::FAILURE;
    }
}
