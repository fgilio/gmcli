<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * Main dispatcher command for gmcli.
 *
 * Routes incoming arguments to appropriate handlers while
 * providing custom help output matching gmcli v0.1.0 format.
 */
class DefaultCommand extends Command
{
    protected $signature = 'default {args?*}';

    protected $description = 'Gmail CLI';

    protected $hidden = true;

    public function handle(): int
    {
        $args = $this->argument('args') ?? [];

        if (empty($args) || in_array($args[0] ?? '', ['--help', '-h', 'help'])) {
            return $this->showHelp();
        }

        $first = $args[0];

        // Route to accounts commands
        if ($first === 'accounts') {
            return $this->routeAccounts(array_slice($args, 1));
        }

        // Route to email-prefixed commands
        if ($this->looksLikeEmail($first)) {
            return $this->routeEmailCommand($first, array_slice($args, 1));
        }

        $this->error("Unknown command: {$first}");
        $this->line('');
        $this->line('Run `gmcli --help` for usage information.');

        return self::FAILURE;
    }

    private function routeAccounts(array $args): int
    {
        if (empty($args)) {
            $this->error('Missing accounts action.');
            $this->line('');
            $this->line('Available actions: credentials, list, add, remove');

            return self::FAILURE;
        }

        $action = $args[0];
        $remaining = array_slice($args, 1);

        return match ($action) {
            'credentials' => $this->call('accounts:credentials', ['file' => $remaining[0] ?? null]),
            'list' => $this->call('accounts:list'),
            'add' => $this->call('accounts:add', [
                'email' => $remaining[0] ?? null,
                '--manual' => in_array('--manual', $remaining, true),
            ]),
            'remove' => $this->call('accounts:remove', ['email' => $remaining[0] ?? null]),
            default => $this->unknownAccountsAction($action),
        };
    }

    private function routeEmailCommand(string $email, array $args): int
    {
        if (empty($args)) {
            $this->error('Missing command.');
            $this->line('');
            $this->line('Available commands: search, thread, labels, drafts, send, url');

            return self::FAILURE;
        }

        $command = $args[0];
        $remaining = array_slice($args, 1);

        return match ($command) {
            'search' => $this->routeSearch($email, $remaining),
            'thread' => $this->routeThread($email, $remaining),
            'labels' => $this->routeLabels($email, $remaining),
            'drafts' => $this->routeDrafts($email, $remaining),
            'send' => $this->routeSend($email, $remaining),
            'url' => $this->routeUrl($email, $remaining),
            default => $this->unknownEmailCommand($command),
        };
    }

    private function routeSearch(string $email, array $args): int
    {
        $parsed = $this->parseArgs($args);
        $query = $parsed['args'][0] ?? $parsed['--query'] ?? null;

        if (empty($query)) {
            $this->error('Missing search query.');
            $this->line('Usage: gmcli <email> search "<query>" [--max N] [--page TOKEN]');

            return self::FAILURE;
        }

        return $this->call('gmail:search', [
            'email' => $email,
            '--query' => $query,
            '--max' => $parsed['--max'] ?? 20,
            '--page' => $parsed['--page'] ?? null,
        ]);
    }

    private function routeThread(string $email, array $args): int
    {
        $parsed = $this->parseArgs($args);
        $threadId = $parsed['args'][0] ?? null;

        if (empty($threadId)) {
            $this->error('Missing thread ID.');
            $this->line('Usage: gmcli <email> thread <threadId> [--download]');

            return self::FAILURE;
        }

        return $this->call('gmail:thread', [
            'email' => $email,
            '--thread-id' => $threadId,
            '--download' => $parsed['--download'] ?? false,
        ]);
    }

    private function routeUrl(string $email, array $args): int
    {
        $parsed = $this->parseArgs($args);
        $threadIds = $parsed['args'] ?? [];

        if (empty($threadIds)) {
            $this->error('Missing thread IDs.');
            $this->line('Usage: gmcli <email> url <threadIds...>');

            return self::FAILURE;
        }

        return $this->call('gmail:url', [
            'email' => $email,
            '--thread-ids' => $threadIds,
        ]);
    }

    private function routeLabels(string $email, array $args): int
    {
        if (empty($args)) {
            $this->error('Missing labels action or thread IDs.');
            $this->line('');
            $this->line('Usage: gmcli <email> labels list');
            $this->line('       gmcli <email> labels <threadIds...> [--add L] [--remove L]');

            return self::FAILURE;
        }

        if ($args[0] === 'list') {
            return $this->call('gmail:labels:list', ['email' => $email]);
        }

        // Parse labels modify args
        $parsed = $this->parseArgs($args);
        $threadIds = $parsed['args'] ?? [];
        $addLabels = $parsed['--add'] ?? null;
        $removeLabels = $parsed['--remove'] ?? null;

        return $this->call('gmail:labels:modify', [
            'email' => $email,
            '--thread-ids' => $threadIds,
            '--add' => $addLabels ? [$addLabels] : [],
            '--remove' => $removeLabels ? [$removeLabels] : [],
        ]);
    }

    private function routeDrafts(string $email, array $args): int
    {
        if (empty($args)) {
            $this->error('Missing drafts action.');
            $this->line('');
            $this->line('Available actions: list, get, delete, send, create');

            return self::FAILURE;
        }

        $action = $args[0];
        $remaining = array_slice($args, 1);
        $parsed = $this->parseArgs($remaining);

        return match ($action) {
            'list' => $this->call('gmail:drafts:list', ['email' => $email]),
            'get' => $this->call('gmail:drafts:get', [
                'email' => $email,
                '--draft-id' => $parsed['args'][0] ?? null,
                '--download' => $parsed['--download'] ?? false,
            ]),
            'delete' => $this->call('gmail:drafts:delete', [
                'email' => $email,
                '--draft-id' => $parsed['args'][0] ?? null,
            ]),
            'send' => $this->call('gmail:drafts:send', [
                'email' => $email,
                '--draft-id' => $parsed['args'][0] ?? null,
            ]),
            'create' => $this->call('gmail:drafts:create', [
                'email' => $email,
                '--to' => $parsed['--to'] ?? null,
                '--subject' => $parsed['--subject'] ?? null,
                '--body' => $parsed['--body'] ?? null,
                '--cc' => $parsed['--cc'] ?? null,
                '--bcc' => $parsed['--bcc'] ?? null,
                '--reply-to' => $parsed['--reply-to'] ?? null,
                '--attach' => $this->collectAttachments($remaining),
            ]),
            default => $this->unknownDraftsAction($action),
        };
    }

    private function routeSend(string $email, array $args): int
    {
        $parsed = $this->parseArgs($args);

        return $this->call('gmail:send', [
            'email' => $email,
            '--to' => $parsed['--to'] ?? null,
            '--subject' => $parsed['--subject'] ?? null,
            '--body' => $parsed['--body'] ?? null,
            '--cc' => $parsed['--cc'] ?? null,
            '--bcc' => $parsed['--bcc'] ?? null,
            '--reply-to' => $parsed['--reply-to'] ?? null,
            '--attach' => $this->collectAttachments($args),
        ]);
    }

    /**
     * Collects multiple --attach arguments.
     */
    private function collectAttachments(array $args): array
    {
        $attachments = [];
        $i = 0;

        while ($i < count($args)) {
            if ($args[$i] === '--attach' && isset($args[$i + 1])) {
                $attachments[] = $args[$i + 1];
                $i += 2;
            } else {
                $i++;
            }
        }

        return $attachments;
    }

    private function callEmailCommand(string $command, string $email, array $args): int
    {
        return $this->call($command, array_merge(
            ['email' => $email],
            $this->parseArgs($args)
        ));
    }

    /**
     * Parses raw arguments into named parameters.
     */
    private function parseArgs(array $args): array
    {
        $parsed = [];
        $positional = [];
        $i = 0;

        while ($i < count($args)) {
            $arg = $args[$i];

            if (str_starts_with($arg, '--')) {
                $key = substr($arg, 2);
                // Check if next arg is a value or another flag
                if (isset($args[$i + 1]) && ! str_starts_with($args[$i + 1], '--')) {
                    $parsed["--{$key}"] = $args[$i + 1];
                    $i += 2;
                } else {
                    $parsed["--{$key}"] = true;
                    $i++;
                }
            } else {
                $positional[] = $arg;
                $i++;
            }
        }

        if (! empty($positional)) {
            $parsed['args'] = $positional;
        }

        return $parsed;
    }

    private function looksLikeEmail(string $value): bool
    {
        return str_contains($value, '@') && str_contains($value, '.');
    }

    private function unknownAccountsAction(string $action): int
    {
        $this->error("Unknown accounts action: {$action}");
        $this->line('');
        $this->line('Available actions: credentials, list, add, remove');

        return self::FAILURE;
    }

    private function unknownEmailCommand(string $command): int
    {
        $this->error("Unknown command: {$command}");
        $this->line('');
        $this->line('Available commands: search, thread, labels, drafts, send, url');

        return self::FAILURE;
    }

    private function unknownDraftsAction(string $action): int
    {
        $this->error("Unknown drafts action: {$action}");
        $this->line('');
        $this->line('Available actions: list, get, delete, send, create');

        return self::FAILURE;
    }

    private function showHelp(): int
    {
        $help = <<<'HELP'
gmcli - Gmail CLI

USAGE

  gmcli accounts <action>                    Account management
  gmcli <email> <command> [options]          Gmail operations

ACCOUNT COMMANDS

  gmcli accounts credentials <file.json>     Set OAuth credentials (once)
  gmcli accounts list                        List configured accounts
  gmcli accounts add <email> [--manual]      Add account (--manual for browserless OAuth)
  gmcli accounts remove <email>              Remove account

GMAIL COMMANDS

  gmcli <email> search <query> [--max N] [--page TOKEN]
      Search threads using Gmail query syntax.
      Returns: thread ID, date, sender, subject, labels.

      Query examples:
        in:inbox, in:sent, in:drafts, in:trash
        is:unread, is:starred, is:important
        from:sender@example.com, to:recipient@example.com
        subject:keyword, has:attachment, filename:pdf
        after:2024/01/01, before:2024/12/31
        label:Work, label:UNREAD
        Combine: "in:inbox is:unread from:boss@company.com"

  gmcli <email> thread <threadId> [--download]
      Get thread with all messages.
      Shows: Message-ID, headers, body, attachments.
      --download saves attachments to ~/.gmcli/attachments/

  gmcli <email> labels list
      List all labels with ID, name, and type.

  gmcli <email> labels <threadIds...> [--add L] [--remove L]
      Modify labels on threads (comma-separated for multiple).
      Accepts label names or IDs (names are case-insensitive).
      System labels: INBOX, UNREAD, STARRED, IMPORTANT, TRASH, SPAM

  gmcli <email> drafts list
      List all drafts. Returns: draft ID, message ID.

  gmcli <email> drafts get <draftId> [--download]
      View draft with attachments.
      --download saves attachments to ~/.gmcli/attachments/

  gmcli <email> drafts delete <draftId>
      Delete a draft.

  gmcli <email> drafts send <draftId>
      Send a draft.

  gmcli <email> drafts create --to <emails> --subject <s> --body <b> [options]
      Create a new draft.

  gmcli <email> send --to <emails> --subject <s> --body <b> [options]
      Send an email directly.

      Options for drafts create / send:
        --to <emails>           Recipients (comma-separated, required)
        --subject <s>           Subject line (required)
        --body <b>              Message body (required)
        --cc <emails>           CC recipients (comma-separated)
        --bcc <emails>          BCC recipients (comma-separated)
        --reply-to <messageId>  Reply to message (sets headers and thread)
        --attach <file>         Attach file (use multiple times for multiple files)

  gmcli <email> url <threadIds...>
      Generate Gmail web URLs for threads.
      Uses canonical URL format with email parameter.

EXAMPLES

  gmcli accounts list
  gmcli you@gmail.com search "in:inbox is:unread"
  gmcli you@gmail.com search "from:boss@company.com" --max 50
  gmcli you@gmail.com thread 19aea1f2f3532db5
  gmcli you@gmail.com thread 19aea1f2f3532db5 --download
  gmcli you@gmail.com labels list
  gmcli you@gmail.com labels abc123 --add Work --remove UNREAD
  gmcli you@gmail.com drafts create --to a@x.com --subject "Hi" --body "Hello"
  gmcli you@gmail.com drafts send r1234567890
  gmcli you@gmail.com send --to a@x.com --subject "Hi" --body "Hello"
  gmcli you@gmail.com send --to a@x.com --subject "Re: Topic" \
      --body "Reply text" --reply-to 19aea1f2f3532db5 --attach file.pdf
  gmcli you@gmail.com url 19aea1f2f3532db5 19aea1f2f3532db6

DATA STORAGE

  ~/.gmcli/.env               OAuth credentials and account token
  ~/.gmcli/attachments/       Downloaded attachments
HELP;

        $this->line($help);

        return self::SUCCESS;
    }
}
