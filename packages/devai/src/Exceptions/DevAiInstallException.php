<?php

declare(strict_types=1);

namespace Marko\DevAi\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\DevAi\Installation\IntelephenseEnsurer;

class DevAiInstallException extends MarkoException
{
    /**
     * @throws static
     */
    public static function alreadyRegistered(string $projectRoot): static
    {
        return new static(
            message: 'Marko marketplace already registered in .claude/settings.json',
            context: 'While installing the claude-code agent at ' . $projectRoot,
            suggestion: 'Pass --force to overwrite the existing registration. This will preserve unrelated user keys but replace marko-prefixed ones.',
        );
    }

    /**
     * @param list<string> $missingPlugins plugin ids `claude plugin list` did not report as enabled
     * @param list<string> $allPlugins every plugin id devai installs, for the copy-paste fix
     */
    public static function claudePluginsNotInstalled(
        array $missingPlugins,
        array $allPlugins,
        string $details,
    ): static {
        $commands = array_map(
            static fn (string $plugin): string => '  claude plugin install ' . $plugin . ' --scope project',
            $allPlugins,
        );

        $missing = implode(', ', $missingPlugins);

        return new static(
            message: 'Claude Code plugins enabled in .claude/settings.json but not installed: ' . $missing,
            context: 'While verifying the claude-code agent install with `claude plugin list`'
                . ($details === '' ? '' : ":\n" . $details),
            suggestion: "Install the plugins from the project root:\n" . implode("\n", $commands)
                . "\nThen restart Claude Code so it loads the Marko MCP server, LSP and skills.",
        );
    }

    public static function npmRequiredForLspDeps(): static
    {
        return new static(
            message: 'intelephense is required for full LSP support but npm is not on PATH',
            suggestion: 'Install Node.js (https://nodejs.org), or pass --skip-lsp-deps to install devai without LSP dependencies.',
        );
    }

    public static function intelephenseInstallFailed(string $stderr): static
    {
        return new static(
            message: 'Failed to auto-install ' . IntelephenseEnsurer::PACKAGE . ' via npm install -g',
            context: $stderr,
            suggestion: 'Run `npm install -g ' . IntelephenseEnsurer::PACKAGE . '` manually, or pass --skip-lsp-deps.',
        );
    }

    public static function invalidConfig(
        string $key,
        string $expected,
        mixed $value,
    ): static {
        return new static(
            message: "Invalid devai config value for '$key': expected $expected, got " . get_debug_type($value),
            context: 'While reading config/devai.php',
            suggestion: "Set '$key' to $expected, or to null to use the default.",
        );
    }
}
