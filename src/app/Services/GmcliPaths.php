<?php

namespace App\Services;

use RuntimeException;

/**
 * Manages gmcli directory structure and paths.
 *
 * Handles creation and permissions for ~/.gmcli/ directory
 * with 0700 permissions for security.
 */
class GmcliPaths
{
    private string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?? $this->defaultBasePath();
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function envFile(): string
    {
        return $this->basePath . '/.env';
    }

    public function attachmentsDir(): string
    {
        return $this->basePath . '/attachments';
    }

    /**
     * Returns path to skill-level .env if it exists.
     *
     * Looks for .env next to the running binary/script.
     * This allows shared credentials to be distributed with the binary.
     */
    public function skillEnvFile(): ?string
    {
        $binaryDir = $this->getBinaryDirectory();
        if (! $binaryDir) {
            return null;
        }

        $envPath = $binaryDir . '/.env';

        return file_exists($envPath) ? $envPath : null;
    }

    /**
     * Determines the directory containing the running binary.
     *
     * Handles both compiled PHAR (micro.sfx) and regular PHP execution.
     */
    private function getBinaryDirectory(): ?string
    {
        // For compiled PHAR, Phar::running(false) returns the binary path
        if (class_exists(\Phar::class)) {
            $pharPath = \Phar::running(false);
            if ($pharPath) {
                return dirname($pharPath);
            }
        }

        // Fallback for regular PHP execution
        $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? ($_SERVER['argv'][0] ?? null);
        if ($scriptPath) {
            $realPath = realpath($scriptPath);
            if ($realPath) {
                return dirname($realPath);
            }
        }

        return null;
    }

    /**
     * Ensures base directory exists with secure permissions.
     *
     * @throws RuntimeException if directory cannot be created
     */
    public function ensureBaseDir(): void
    {
        if (! is_dir($this->basePath)) {
            if (! mkdir($this->basePath, 0700, true)) {
                throw new RuntimeException("Failed to create directory: {$this->basePath}");
            }
        }

        $this->ensurePermissions($this->basePath, 0700);
    }

    /**
     * Ensures attachments directory exists.
     *
     * @throws RuntimeException if directory cannot be created
     */
    public function ensureAttachmentsDir(): void
    {
        $this->ensureBaseDir();
        $dir = $this->attachmentsDir();

        if (! is_dir($dir)) {
            if (! mkdir($dir, 0700, true)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }
    }

    /**
     * Checks if the base directory exists.
     */
    public function exists(): bool
    {
        return is_dir($this->basePath);
    }

    /**
     * Returns the current permissions of the base directory.
     */
    public function getPermissions(): ?int
    {
        if (! $this->exists()) {
            return null;
        }

        return fileperms($this->basePath) & 0777;
    }

    /**
     * Checks if directory permissions are secure (0700 or stricter).
     */
    public function hasSecurePermissions(): bool
    {
        $perms = $this->getPermissions();
        if ($perms === null) {
            return false;
        }

        // Check that group and others have no permissions
        return ($perms & 0077) === 0;
    }

    private function defaultBasePath(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: '');
        if (empty($home)) {
            throw new RuntimeException('Unable to determine home directory');
        }

        return $home . '/.gmcli';
    }

    private function ensurePermissions(string $path, int $mode): void
    {
        $current = fileperms($path) & 0777;
        if ($current !== $mode) {
            chmod($path, $mode);
        }
    }
}
