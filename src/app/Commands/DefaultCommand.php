<?php

namespace App\Commands;

use App\Services\GmcliEnv;
use App\Services\GmcliPaths;
use Fgilio\AgentSkillFoundation\Router\ParsedInput;
use Fgilio\AgentSkillFoundation\Router\Router;
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

    /** Gmail commands that can use default email */
    private array $gmailCommands = ['search', 'thread', 'labels', 'drafts', 'send', 'url'];

    public function __construct(private Router $router)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->ignoreValidationErrors();
    }

    protected function shouldOutputJson(): bool
    {
        return in_array('--json', $_SERVER['argv'], true);
    }

    protected function jsonError(string $message, bool $includeCommands = false): int
    {
        if ($this->shouldOutputJson()) {
            $data = ['error' => $message];
            if ($includeCommands) {
                $data['valid_commands'] = $this->gmailCommands;
            }
            fwrite(STDERR, json_encode($data, JSON_PRETTY_PRINT)."\n");
        } else {
            $this->error($message);
        }

        return self::FAILURE;
    }

    public function handle(): int
    {
        $parsed = $this->router->parse($this);
        $first = $parsed->subcommand();

        // Handle help
        if (empty($first) || in_array($first, ['--help', '-h', 'help'])) {
            return $this->showHelp();
        }

        // Route to accounts commands
        if ($first === 'accounts') {
            return $this->routeAccounts($parsed->shift(1));
        }

        // Route to build command
        if ($first === 'build') {
            return $this->call('build', [
                '--no-install' => $parsed->hasFlag('no-install'),
                '--keep-dev' => $parsed->hasFlag('keep-dev'),
            ]);
        }

        // Route Gmail commands - determine email
        if ($this->looksLikeEmail($first)) {
            return $this->routeEmailCommand($first, $parsed->shift(1));
        }

        // Check if it's a Gmail command using default email
        if (in_array($first, $this->gmailCommands)) {
            $email = $this->getDefaultEmail();
            if (! $email) {
                if ($this->shouldOutputJson()) {
                    return $this->jsonError('No default email configured.');
                }
                $this->error('No default email configured.');
                $this->line('');
                $this->line('Either specify email: gmcli <email> ' . $first . ' ...');
                $this->line('Or add an account:    gmcli accounts add <email>');

                return self::FAILURE;
            }

            return $this->routeEmailCommand($email, $parsed);
        }

        return $this->jsonError("Unknown command: {$first}", true);
    }

    private function getDefaultEmail(): ?string
    {
        $env = new GmcliEnv(new GmcliPaths);

        return $env->get('GMAIL_ADDRESS');
    }

    private function routeAccounts(ParsedInput $p): int
    {
        $action = $p->subcommand();
        if (empty($action)) {
            return $this->jsonError('Missing accounts action.');
        }

        $shifted = $p->shift(1);
        $jsonFlag = $this->shouldOutputJson();

        return match ($action) {
            'credentials' => $this->call('accounts:credentials', ['file' => $shifted->arg(0)]),
            'list' => $this->call('accounts:list', ['--json' => $jsonFlag]),
            'add' => $this->call('accounts:add', [
                'email' => $shifted->arg(0),
                '--manual' => $shifted->hasFlag('manual'),
            ]),
            'remove' => $this->call('accounts:remove', ['email' => $shifted->arg(0)]),
            default => $this->unknownAccountsAction($action),
        };
    }

    private function routeEmailCommand(string $email, ParsedInput $p): int
    {
        $command = $p->subcommand();
        if (empty($command)) {
            return $this->jsonError('Missing command.');
        }

        $shifted = $p->shift(1);

        return match ($command) {
            'search' => $this->routeSearch($email, $shifted),
            'thread' => $this->routeThread($email, $shifted),
            'labels' => $this->routeLabels($email, $shifted),
            'drafts' => $this->routeDrafts($email, $shifted),
            'send' => $this->routeSend($email, $shifted),
            'url' => $this->routeUrl($email, $shifted),
            default => $this->unknownEmailCommand($command),
        };
    }

    private function routeSearch(string $email, ParsedInput $p): int
    {
        $query = $p->arg(0) ?? $p->scanOption('query');

        if (empty($query)) {
            return $this->jsonError('Missing search query.');
        }

        return $this->call('gmail:search', [
            'email' => $email,
            '--query' => $query,
            '--max' => $p->scanOption('max', null, 20),
            '--page' => $p->scanOption('page'),
            '--json' => $this->shouldOutputJson(),
        ]);
    }

    private function routeThread(string $email, ParsedInput $p): int
    {
        $threadId = $p->arg(0);

        if (empty($threadId)) {
            return $this->jsonError('Missing thread ID.');
        }

        return $this->call('gmail:thread', [
            'email' => $email,
            '--thread-id' => $threadId,
            '--download' => $p->hasFlag('download'),
            '--json' => $this->shouldOutputJson(),
        ]);
    }

    private function routeUrl(string $email, ParsedInput $p): int
    {
        $threadIds = $p->remainingArgs();

        if (empty($threadIds)) {
            return $this->jsonError('Missing thread IDs.');
        }

        return $this->call('gmail:url', [
            'email' => $email,
            '--thread-ids' => $threadIds,
            '--json' => $this->shouldOutputJson(),
        ]);
    }

    private function routeLabels(string $email, ParsedInput $p): int
    {
        $first = $p->subcommand();
        if (empty($first)) {
            return $this->jsonError('Missing labels action or thread IDs.');
        }

        $jsonFlag = $this->shouldOutputJson();

        if ($first === 'list') {
            return $this->call('gmail:labels:list', ['email' => $email, '--json' => $jsonFlag]);
        }

        // Thread IDs for label modification
        $threadIds = $p->remainingArgs();
        $addLabels = $p->scanOption('add');
        $removeLabels = $p->scanOption('remove');

        return $this->call('gmail:labels:modify', [
            'email' => $email,
            '--thread-ids' => $threadIds,
            '--add' => $addLabels ? [$addLabels] : [],
            '--remove' => $removeLabels ? [$removeLabels] : [],
            '--json' => $jsonFlag,
        ]);
    }

    private function routeDrafts(string $email, ParsedInput $p): int
    {
        $action = $p->subcommand();
        if (empty($action)) {
            return $this->jsonError('Missing drafts action.');
        }

        $shifted = $p->shift(1);
        $jsonFlag = $this->shouldOutputJson();

        return match ($action) {
            'list' => $this->call('gmail:drafts:list', ['email' => $email, '--json' => $jsonFlag]),
            'get' => $this->call('gmail:drafts:get', [
                'email' => $email,
                '--draft-id' => $shifted->arg(0),
                '--download' => $shifted->hasFlag('download'),
                '--json' => $jsonFlag,
            ]),
            'delete' => $this->call('gmail:drafts:delete', [
                'email' => $email,
                '--draft-id' => $shifted->arg(0),
                '--json' => $jsonFlag,
            ]),
            'send' => $this->call('gmail:drafts:send', [
                'email' => $email,
                '--draft-id' => $shifted->arg(0),
                '--json' => $jsonFlag,
            ]),
            'create' => $this->call('gmail:drafts:create', [
                'email' => $email,
                '--to' => $shifted->scanOption('to'),
                '--subject' => $shifted->scanOption('subject'),
                '--body' => $shifted->scanOption('body'),
                '--cc' => $shifted->scanOption('cc'),
                '--bcc' => $shifted->scanOption('bcc'),
                '--reply-to' => $shifted->scanOption('reply-to'),
                '--attach' => $shifted->collectOption('attach'),
                '--json' => $jsonFlag,
            ]),
            default => $this->unknownDraftsAction($action),
        };
    }

    private function routeSend(string $email, ParsedInput $p): int
    {
        return $this->call('gmail:send', [
            'email' => $email,
            '--to' => $p->scanOption('to'),
            '--subject' => $p->scanOption('subject'),
            '--body' => $p->scanOption('body'),
            '--cc' => $p->scanOption('cc'),
            '--bcc' => $p->scanOption('bcc'),
            '--reply-to' => $p->scanOption('reply-to'),
            '--attach' => $p->collectOption('attach'),
            '--json' => $this->shouldOutputJson(),
        ]);
    }

    private function looksLikeEmail(string $value): bool
    {
        return str_contains($value, '@') && str_contains($value, '.');
    }

    private function unknownAccountsAction(string $action): int
    {
        return $this->jsonError("Unknown accounts action: {$action}");
    }

    private function unknownEmailCommand(string $command): int
    {
        return $this->jsonError("Unknown command: {$command}");
    }

    private function unknownDraftsAction(string $action): int
    {
        return $this->jsonError("Unknown drafts action: {$action}");
    }

    private function showHelp(): int
    {
        $help = <<<'HELP'
gmcli - Gmail CLI

USAGE

  gmcli accounts <action>                    Account management
  gmcli [email] <command> [options]          Gmail operations

  Email is optional if you have an account configured.
  When omitted, uses the email from `gmcli accounts add`.

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
