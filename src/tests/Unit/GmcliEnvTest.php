<?php

use App\Services\GmcliEnv;
use App\Services\GmcliPaths;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/gmcli-test-'.uniqid();
    mkdir($this->tempDir, 0700, true);
    $this->paths = new GmcliPaths($this->tempDir);
    $this->env = new GmcliEnv($this->paths);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    }
});

describe('parsing', function () {
    it('parses simple key=value pairs', function () {
        file_put_contents($this->paths->envFile(), "FOO=bar\nBAZ=qux\n");

        $env = new GmcliEnv($this->paths);

        expect($env->get('FOO'))->toBe('bar');
        expect($env->get('BAZ'))->toBe('qux');
    });

    it('parses quoted values', function () {
        file_put_contents($this->paths->envFile(), "FOO=\"bar baz\"\nSINGLE='quoted'\n");

        $env = new GmcliEnv($this->paths);

        expect($env->get('FOO'))->toBe('bar baz');
        expect($env->get('SINGLE'))->toBe('quoted');
    });

    it('skips comments and empty lines', function () {
        file_put_contents($this->paths->envFile(), "# comment\n\nFOO=bar\n# another\nBAZ=qux\n");

        $env = new GmcliEnv($this->paths);

        expect($env->get('FOO'))->toBe('bar');
        expect($env->get('BAZ'))->toBe('qux');
        expect($env->all())->toHaveCount(2);
    });

    it('handles values with equals signs', function () {
        file_put_contents($this->paths->envFile(), "URL=https://example.com?foo=bar&baz=qux\n");

        $env = new GmcliEnv($this->paths);

        expect($env->get('URL'))->toBe('https://example.com?foo=bar&baz=qux');
    });
});

describe('writing', function () {
    it('saves values with atomic write', function () {
        $this->env->set('FOO', 'bar');
        $this->env->set('BAZ', 'qux');
        $this->env->save();

        $content = file_get_contents($this->paths->envFile());

        expect($content)->toContain('FOO=bar');
        expect($content)->toContain('BAZ=qux');
    });

    it('sets secure permissions on file', function () {
        $this->env->set('FOO', 'bar');
        $this->env->save();

        $perms = fileperms($this->paths->envFile()) & 0777;

        expect($perms)->toBe(0600);
    });

    it('quotes values with special characters', function () {
        $this->env->set('SPACES', 'foo bar');
        $this->env->set('HASH', 'foo#bar');
        $this->env->save();

        $content = file_get_contents($this->paths->envFile());

        expect($content)->toContain('SPACES="foo bar"');
        expect($content)->toContain('HASH="foo#bar"');
    });

    it('preserves known keys order', function () {
        $this->env->set('GMAIL_ADDRESS', 'test@gmail.com');
        $this->env->set('GOOGLE_CLIENT_ID', 'client123');
        $this->env->set('GMAIL_REFRESH_TOKEN', 'token');
        $this->env->set('GOOGLE_CLIENT_SECRET', 'secret');
        $this->env->save();

        $content = file_get_contents($this->paths->envFile());
        $lines = array_filter(explode("\n", trim($content)));

        // Known keys should be in order
        expect($lines[0])->toStartWith('GOOGLE_CLIENT_ID=');
        expect($lines[1])->toStartWith('GOOGLE_CLIENT_SECRET=');
        expect($lines[2])->toStartWith('GMAIL_ADDRESS=');
        expect($lines[3])->toStartWith('GMAIL_REFRESH_TOKEN=');
    });
});

describe('permissions', function () {
    it('detects insecure file permissions', function () {
        file_put_contents($this->paths->envFile(), "FOO=bar\n");
        chmod($this->paths->envFile(), 0644);

        $env = new GmcliEnv($this->paths);

        expect($env->hasSecurePermissions())->toBeFalse();
        expect($env->getPermissionWarning())->toContain('insecure permissions');
    });

    it('accepts secure permissions', function () {
        file_put_contents($this->paths->envFile(), "FOO=bar\n");
        chmod($this->paths->envFile(), 0600);

        $env = new GmcliEnv($this->paths);

        expect($env->hasSecurePermissions())->toBeTrue();
        expect($env->getPermissionWarning())->toBeNull();
    });
});

describe('alias matching', function () {
    it('matches primary email case-insensitively', function () {
        $this->env->set('GMAIL_ADDRESS', 'Test@Gmail.com');
        $this->env->save();
        $this->env->reload();

        expect($this->env->matchesEmail('test@gmail.com'))->toBeTrue();
        expect($this->env->matchesEmail('TEST@GMAIL.COM'))->toBeTrue();
        expect($this->env->matchesEmail('Test@Gmail.com'))->toBeTrue();
    });

    it('matches aliases case-insensitively', function () {
        $this->env->set('GMAIL_ADDRESS', 'primary@gmail.com');
        $this->env->set('GMAIL_ADDRESS_ALIASES', 'alias1@gmail.com, Alias2@Gmail.com');
        $this->env->save();
        $this->env->reload();

        expect($this->env->matchesEmail('alias1@gmail.com'))->toBeTrue();
        expect($this->env->matchesEmail('ALIAS2@GMAIL.COM'))->toBeTrue();
    });

    it('returns false for non-matching emails', function () {
        $this->env->set('GMAIL_ADDRESS', 'test@gmail.com');
        $this->env->save();
        $this->env->reload();

        expect($this->env->matchesEmail('other@gmail.com'))->toBeFalse();
    });

    it('parses aliases from CSV', function () {
        $this->env->set('GMAIL_ADDRESS_ALIASES', 'a@x.com, b@x.com, c@x.com');
        $this->env->save();
        $this->env->reload();

        $aliases = $this->env->getAliases();

        expect($aliases)->toBe(['a@x.com', 'b@x.com', 'c@x.com']);
    });
});

describe('credentials and account checks', function () {
    it('detects when credentials are configured', function () {
        expect($this->env->hasCredentials())->toBeFalse();

        $this->env->set('GOOGLE_CLIENT_ID', 'id');
        $this->env->set('GOOGLE_CLIENT_SECRET', 'secret');

        expect($this->env->hasCredentials())->toBeTrue();
    });

    it('detects when account is configured', function () {
        expect($this->env->hasAccount())->toBeFalse();

        $this->env->set('GMAIL_ADDRESS', 'test@gmail.com');
        $this->env->set('GMAIL_REFRESH_TOKEN', 'token');

        expect($this->env->hasAccount())->toBeTrue();
    });
});
