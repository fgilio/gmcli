<?php

namespace App\Commands\Accounts;

use App\Services\GmcliEnv;
use App\Services\OAuthService;
use LaravelZero\Framework\Commands\Command;
use RuntimeException;

/**
 * Adds a Gmail account via OAuth flow.
 *
 * Supports two modes:
 * - Auto (default): Opens browser, starts local callback server
 * - Manual (--manual): User copies/pastes authorization URL
 */
class AddCommand extends Command
{
    protected $signature = 'accounts:add
        {email? : Email address to add}
        {--manual : Use browserless OAuth flow (manual paste)}';

    protected $description = 'Add a Gmail account via OAuth';

    protected $hidden = true;

    public function handle(GmcliEnv $env): int
    {
        $email = $this->argument('email');

        if (empty($email)) {
            $this->error('Missing email address.');
            $this->line('');
            $this->line('Usage: gmcli accounts add <email> [--manual]');

            return self::FAILURE;
        }

        if (! $env->hasCredentials()) {
            $this->error('No credentials configured.');
            $this->line('Run: gmcli accounts credentials <file.json> first.');

            return self::FAILURE;
        }

        if ($env->hasAccount()) {
            $existingEmail = $env->getEmail();
            $this->error("Account already configured: {$existingEmail}");
            $this->line('Remove it first: gmcli accounts remove ' . $existingEmail);

            return self::FAILURE;
        }

        $oauth = new OAuthService(
            $env->get('GOOGLE_CLIENT_ID'),
            $env->get('GOOGLE_CLIENT_SECRET'),
            120
        );

        try {
            if ($this->option('manual')) {
                $tokens = $this->runManualFlow($oauth);
            } else {
                $tokens = $this->runAutoFlow($oauth);
            }

            $env->set('GMAIL_ADDRESS', $email);
            $env->set('GMAIL_REFRESH_TOKEN', $tokens['refresh_token']);
            $env->save();

            $this->info("Account added: {$email}");

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function runAutoFlow(OAuthService $oauth): array
    {
        $this->line('Opening browser for authentication...');
        $this->line('');

        return $oauth->runAutoFlow(function (string $url) {
            $this->line('If the browser does not open, visit:');
            $this->line($url);
            $this->line('');

            // Open browser on macOS
            exec("open " . escapeshellarg($url) . " 2>/dev/null &");
        });
    }

    private function runManualFlow(OAuthService $oauth): array
    {
        return $oauth->runManualFlow(
            function (string $url) {
                $this->line('Open this URL in your browser:');
                $this->line('');
                $this->line($url);
                $this->line('');
            },
            function () {
                $this->line('After authorizing, your browser will show an error page.');
                $this->line('This is expected. Copy the URL from the address bar.');
                $this->line('');

                return $this->ask('Paste the redirect URL here');
            }
        );
    }
}
