<?php

declare(strict_types=1);

namespace Marko\Skeleton\Prompts;

class DevAiPrompt
{
    /**
     * Returns true if user wants to install devai (default true).
     *
     * @param resource $input STDIN
     */
    public function ask(mixed $input = null): bool
    {
        $input ??= STDIN;
        echo 'Install marko/devai for AI-assisted development? (recommended) [Y/n]: ';
        $line = fgets($input);
        if ($line === false) {
            return true;
        }
        $trimmed = trim(strtolower($line));
        if ($trimmed === '' || $trimmed === 'y' || $trimmed === 'yes') {
            return true;
        }

        return false;
    }

    /**
     * Run `composer require --dev marko/devai` in $projectRoot.
     * Returns exit code.
     */
    public function install(string $projectRoot): int
    {
        $cmd = sprintf('cd %s && composer require --dev marko/devai 2>&1', escapeshellarg($projectRoot));
        passthru($cmd, $exitCode);

        return $exitCode;
    }

    /** Record choice in skeleton config to avoid re-prompting */
    public function recordChoice(string $projectRoot, bool $accepted): void
    {
        $configDir = $projectRoot . '/.marko';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }
        $configPath = $configDir . '/skeleton.json';
        $config = is_file($configPath) ? (json_decode((string) file_get_contents($configPath), true) ?: []) : [];
        $config['devaiPromptAnswered'] = true;
        $config['devaiAccepted'] = $accepted;
        file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    }

    public function alreadyAnswered(string $projectRoot): bool
    {
        $configPath = $projectRoot . '/.marko/skeleton.json';
        if (!is_file($configPath)) {
            return false;
        }
        $config = json_decode((string) file_get_contents($configPath), true);

        return is_array($config) && ($config['devaiPromptAnswered'] ?? false) === true;
    }
}
