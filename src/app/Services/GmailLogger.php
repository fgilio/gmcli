<?php

namespace App\Services;

use Illuminate\Console\OutputStyle;

/**
 * Handles verbose/debug logging for Gmail operations.
 *
 * Levels:
 * - debug: Detailed internal operations (--debug)
 * - verbose: User-facing progress info (--verbose)
 */
class GmailLogger
{
    private OutputStyle $output;

    private bool $verbose;

    private bool $debug;

    public function __construct(OutputStyle $output, bool $verbose = false, bool $debug = false)
    {
        $this->output = $output;
        $this->verbose = $verbose || $debug;
        $this->debug = $debug;
    }

    public function log(string $level, string $message): void
    {
        match ($level) {
            'debug' => $this->debug && $this->output->writeln("<fg=gray>[DEBUG] {$message}</>"),
            'verbose' => $this->verbose && $this->output->writeln("<fg=cyan>[INFO] {$message}</>"),
            default => null,
        };
    }

    public function debug(string $message): void
    {
        $this->log('debug', $message);
    }

    public function verbose(string $message): void
    {
        $this->log('verbose', $message);
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }
}
