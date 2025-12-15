<?php

namespace App\Services;

use RuntimeException;

/**
 * Manages gmcli environment configuration.
 *
 * Handles reading and writing ~/.gmcli/.env file with
 * atomic writes and secure permissions (0600).
 */
class GmcliEnv
{
    private const REQUIRED_FILE_PERMS = 0600;

    private const KNOWN_KEYS = [
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
        'GMAIL_ADDRESS',
        'GMAIL_REFRESH_TOKEN',
        'GMAIL_ADDRESS_ALIASES',
    ];

    private GmcliPaths $paths;
    private array $values = [];
    private bool $loaded = false;

    public function __construct(GmcliPaths $paths)
    {
        $this->paths = $paths;
    }

    /**
     * Gets a configuration value.
     */
    public function get(string $key, ?string $default = null): ?string
    {
        $this->ensureLoaded();

        return $this->values[$key] ?? $default;
    }

    /**
     * Sets a configuration value.
     */
    public function set(string $key, string $value): self
    {
        $this->ensureLoaded();
        $this->values[$key] = $value;

        return $this;
    }

    /**
     * Removes a configuration value.
     */
    public function remove(string $key): self
    {
        $this->ensureLoaded();
        unset($this->values[$key]);

        return $this;
    }

    /**
     * Checks if a key exists.
     */
    public function has(string $key): bool
    {
        $this->ensureLoaded();

        return isset($this->values[$key]);
    }

    /**
     * Returns all configuration values.
     */
    public function all(): array
    {
        $this->ensureLoaded();

        return $this->values;
    }

    /**
     * Checks if credentials are configured.
     */
    public function hasCredentials(): bool
    {
        return $this->has('GOOGLE_CLIENT_ID') && $this->has('GOOGLE_CLIENT_SECRET');
    }

    /**
     * Checks if an account is configured.
     */
    public function hasAccount(): bool
    {
        return $this->has('GMAIL_ADDRESS') && $this->has('GMAIL_REFRESH_TOKEN');
    }

    /**
     * Gets the configured email address.
     */
    public function getEmail(): ?string
    {
        return $this->get('GMAIL_ADDRESS');
    }

    /**
     * Gets all configured email aliases as an array.
     */
    public function getAliases(): array
    {
        $aliases = $this->get('GMAIL_ADDRESS_ALIASES');
        if (empty($aliases)) {
            return [];
        }

        return array_map('trim', explode(',', $aliases));
    }

    /**
     * Checks if the given email matches the configured account or aliases.
     */
    public function matchesEmail(string $email): bool
    {
        $email = strtolower(trim($email));
        $primary = $this->getEmail();

        if ($primary && strtolower($primary) === $email) {
            return true;
        }

        foreach ($this->getAliases() as $alias) {
            if (strtolower($alias) === $email) {
                return true;
            }
        }

        return false;
    }

    /**
     * Saves configuration to file with atomic write.
     *
     * @throws RuntimeException if write fails
     */
    public function save(): void
    {
        $this->paths->ensureBaseDir();

        $content = $this->serialize();
        $path = $this->paths->envFile();
        $tempPath = $path . '.tmp.' . getmypid();

        // Write to temp file first
        if (file_put_contents($tempPath, $content) === false) {
            throw new RuntimeException("Failed to write to: {$tempPath}");
        }

        // Set permissions before rename
        if (! chmod($tempPath, self::REQUIRED_FILE_PERMS)) {
            unlink($tempPath);
            throw new RuntimeException("Failed to set permissions on: {$tempPath}");
        }

        // Atomic rename
        if (! rename($tempPath, $path)) {
            unlink($tempPath);
            throw new RuntimeException("Failed to rename temp file to: {$path}");
        }
    }

    /**
     * Reloads configuration from file.
     */
    public function reload(): self
    {
        $this->loaded = false;
        $this->values = [];
        $this->ensureLoaded();

        return $this;
    }

    /**
     * Checks if .env file exists.
     */
    public function exists(): bool
    {
        return file_exists($this->paths->envFile());
    }

    /**
     * Returns file permissions or null if file doesn't exist.
     */
    public function getFilePermissions(): ?int
    {
        $path = $this->paths->envFile();
        if (! file_exists($path)) {
            return null;
        }

        return fileperms($path) & 0777;
    }

    /**
     * Checks if file permissions are secure (0600 or stricter).
     */
    public function hasSecurePermissions(): bool
    {
        $perms = $this->getFilePermissions();
        if ($perms === null) {
            return true; // No file = secure
        }

        // Check that group and others have no permissions
        return ($perms & 0077) === 0;
    }

    /**
     * Returns a warning message if permissions are insecure.
     */
    public function getPermissionWarning(): ?string
    {
        if ($this->hasSecurePermissions()) {
            return null;
        }

        $perms = $this->getFilePermissions();
        $octal = decoct($perms);

        return "Warning: {$this->paths->envFile()} has insecure permissions (0{$octal}). "
            . "Expected 0600. Run: chmod 600 {$this->paths->envFile()}";
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $path = $this->paths->envFile();
        if (file_exists($path)) {
            $this->values = $this->parse(file_get_contents($path));
        }

        $this->loaded = true;
    }

    /**
     * Parses dotenv content into array.
     */
    private function parse(string $content): array
    {
        $values = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Parse KEY=value
            if (! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * Serializes values to dotenv format.
     */
    private function serialize(): string
    {
        $lines = [];

        // Write known keys first in order
        foreach (self::KNOWN_KEYS as $key) {
            if (isset($this->values[$key])) {
                $lines[] = $this->formatLine($key, $this->values[$key]);
            }
        }

        // Write any unknown keys after
        foreach ($this->values as $key => $value) {
            if (! in_array($key, self::KNOWN_KEYS, true)) {
                $lines[] = $this->formatLine($key, $value);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatLine(string $key, string $value): string
    {
        // Quote value if it contains special characters
        if (preg_match('/[\s#\'"]/', $value)) {
            $value = '"' . addcslashes($value, '"\\') . '"';
        }

        return "{$key}={$value}";
    }
}
