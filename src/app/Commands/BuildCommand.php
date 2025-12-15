<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * Builds self-contained binary from Laravel Zero project.
 *
 * Wraps phpcli-build from the php-cli skill to create
 * a standalone executable combining micro.sfx + PHAR.
 */
class BuildCommand extends Command
{
    protected $signature = 'build {--no-install : Only build, do not copy to skill root}';

    protected $description = 'Build self-contained binary';

    public function handle(): int
    {
        $projectDir = dirname(__DIR__, 2);
        $skillRoot = dirname($projectDir);
        $microPath = $projectDir.'/buildroot/bin/micro.sfx';

        // Check micro.sfx exists
        if (! file_exists($microPath)) {
            $this->error('micro.sfx not found at: '.$microPath);
            $this->line('');
            $this->line('Run these commands first:');
            $this->line('  phpcli-spc-setup --doctor');
            $this->line('  phpcli-spc-build');

            return self::FAILURE;
        }

        $this->info('Building PHAR...');

        // Build PHAR using Laravel Zero's bundled Box
        $boxPath = $projectDir.'/vendor/laravel-zero/framework/bin/box';
        if (! file_exists($boxPath)) {
            $this->error('Box not found. Run: composer install');

            return self::FAILURE;
        }

        // Ensure builds directory exists
        $buildsDir = $projectDir.'/builds';
        if (! is_dir($buildsDir)) {
            mkdir($buildsDir, 0755, true);
        }

        // Run Box compile
        $boxCmd = sprintf(
            'cd %s && php %s compile --config=%s 2>&1',
            escapeshellarg($projectDir),
            escapeshellarg($boxPath),
            escapeshellarg($projectDir.'/box.json')
        );

        $output = [];
        $exitCode = 0;
        exec($boxCmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('Box compile failed:');
            $this->line(implode("\n", $output));

            return self::FAILURE;
        }

        $pharPath = $buildsDir.'/gmcli.phar';
        if (! file_exists($pharPath)) {
            $this->error('PHAR not created at: '.$pharPath);

            return self::FAILURE;
        }

        $pharSize = round(filesize($pharPath) / 1024 / 1024, 2);
        $this->line("  PHAR: {$pharSize}MB");

        $this->info('Combining with micro.sfx...');

        // Combine micro.sfx + PHAR
        $binaryPath = $buildsDir.'/gmcli';
        $combineCmd = sprintf(
            'cat %s %s > %s && chmod +x %s',
            escapeshellarg($microPath),
            escapeshellarg($pharPath),
            escapeshellarg($binaryPath),
            escapeshellarg($binaryPath)
        );

        exec($combineCmd, $output, $exitCode);

        if ($exitCode !== 0 || ! file_exists($binaryPath)) {
            $this->error('Failed to combine binary');

            return self::FAILURE;
        }

        // Clean up PHAR
        unlink($pharPath);

        $binarySize = round(filesize($binaryPath) / 1024 / 1024, 2);
        $this->line("  Binary: {$binarySize}MB");

        // Install to skill root unless --no-install
        if (! $this->option('no-install')) {
            $installPath = $skillRoot.'/gmcli';

            $this->info('Installing to skill root...');

            if (! copy($binaryPath, $installPath)) {
                $this->error('Failed to copy to: '.$installPath);

                return self::FAILURE;
            }

            chmod($installPath, 0755);
            $this->line("  Installed: {$installPath}");
        }

        $this->newLine();
        $this->info('Build complete!');

        return self::SUCCESS;
    }
}
