<?php

use App\Services\GmcliPaths;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/gmcli-test-'.uniqid();
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        // Remove all files and directories
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

it('returns correct paths', function () {
    $paths = new GmcliPaths($this->tempDir);

    expect($paths->basePath())->toBe($this->tempDir);
    expect($paths->envFile())->toBe($this->tempDir.'/.env');
    expect($paths->attachmentsDir())->toBe($this->tempDir.'/attachments');
});

it('creates base directory with secure permissions', function () {
    $paths = new GmcliPaths($this->tempDir);

    expect($paths->exists())->toBeFalse();

    $paths->ensureBaseDir();

    expect($paths->exists())->toBeTrue();
    expect($paths->getPermissions())->toBe(0700);
    expect($paths->hasSecurePermissions())->toBeTrue();
});

it('creates attachments directory', function () {
    $paths = new GmcliPaths($this->tempDir);

    $paths->ensureAttachmentsDir();

    expect(is_dir($paths->attachmentsDir()))->toBeTrue();
});

it('detects insecure permissions', function () {
    $paths = new GmcliPaths($this->tempDir);
    mkdir($this->tempDir, 0755, true);

    expect($paths->hasSecurePermissions())->toBeFalse();
    expect($paths->getPermissions())->toBe(0755);
});

it('fixes permissions on ensureBaseDir', function () {
    $paths = new GmcliPaths($this->tempDir);
    mkdir($this->tempDir, 0755, true);

    $paths->ensureBaseDir();

    expect($paths->getPermissions())->toBe(0700);
});
