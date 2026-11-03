<?php

declare(strict_types=1);

namespace Marko\DevAi\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Installation\AgentRegistry;
use Marko\DevAi\Installation\DocsDriverResolver;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Installation\InstallationOrchestrator;
use Marko\DevAi\Installation\IntelephenseEnsurer;
use Marko\DevAi\Process\CommandRunnerInterface;

#[Command(
    name: 'devai:install',
    description: 'Install Marko AI development tooling for selected agents',
    flags: ['force', 'update-gitignore', 'skip-lsp-deps', 'yes'],
    destructive: true,
    options: ['agents'],
)]
readonly class InstallCommand implements CommandInterface
{
    public function __construct(
        private InstallationOrchestrator $orchestrator,
        private AgentRegistry $registry,
        private DocsDriverResolver $docsDriverResolver,
        private ConfirmationPrompterInterface $confirmationPrompter,
        private CommandRunnerInterface $commandRunner,
    ) {}

    /**
     * @throws DevAiInstallException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $force = $input->hasOption('force');
        $agentsArg = $input->getOption('agents');
        $gitignoreArg = $input->hasOption('update-gitignore');
        $skipLspDeps = $input->hasOption('skip-lsp-deps');

        $projectRoot = (string) getcwd();

        if ($agentsArg !== null) {
            $context = new InstallationContext(
                selectedAgents: explode(',', $agentsArg),
                force: $force,
                updateGitignore: $gitignoreArg,
                skipLspDeps: $skipLspDeps,
            );
        } else {
            $detected = [];
            foreach ($this->registry->all($projectRoot) as $name => $agent) {
                if ($agent->isInstalled()) {
                    $detected[] = $name;
                }
            }
            $context = $this->buildContextFromDetection($detected, $force, $gitignoreArg, $skipLspDeps, $output);
        }

        if (!$context->skipLspDeps && $this->declinesGlobalLspInstall($context, $input->hasOption('yes'), $output)) {
            $context = new InstallationContext(
                selectedAgents: $context->selectedAgents,
                force: $context->force,
                updateGitignore: $context->updateGitignore,
                skipLspDeps: true,
            );
        }

        $this->maybeInstallDocsDriver($output, $projectRoot);

        $result = $this->orchestrator->install(
            $context,
            $projectRoot,
            static function (string $message) use ($output): void {
                $output->writeLine($message);
            },
        );

        if ($result['status'] === 'skipped') {
            $output->writeLine($result['message'] ?? '');

            return 0;
        }

        $output->writeLine('Installation summary:');
        foreach ($result['log'] ?? [] as $line) {
            $output->writeLine("  - $line");
        }

        $this->maybePrintClaudeMultiInstanceTip($context->selectedAgents, $output);

        return 0;
    }

    /**
     * Surface the Claude Code multi-instance gotcha once Claude Code is among the
     * installed agents. Running several Claude Code instances concurrently makes
     * them contend on a single global ~/.claude.json, which Claude Code rewrites
     * constantly — the contention causes every MCP server (marko-mcp included) to
     * disconnect and reconnect in lockstep. This is a Claude Code behavior, not a
     * Marko one, so we only point at the documented per-project config-isolation
     * workaround rather than touching the user's shell.
     *
     * @param list<string> $selectedAgents
     */
    private function maybePrintClaudeMultiInstanceTip(
        array $selectedAgents,
        Output $output,
    ): void {
        if (!in_array('claude-code', $selectedAgents, true)) {
            return;
        }

        $output->writeLine('');
        $output->writeLine('Tip: if you run multiple Claude Code instances at once, they contend on a');
        $output->writeLine('single ~/.claude.json and MCP servers (including marko-mcp) can disconnect and');
        $output->writeLine('reconnect repeatedly. To isolate Claude Code config per project, see:');
        $output->writeLine(
            '  https://marko.build/docs/ai-assisted-development/troubleshooting/#multiple-claude-code-instances-disconnect-mcp-servers',
        );
    }

    /**
     * Ask before Claude Code's LSP dependency is installed globally with npm, since a
     * global install touches the machine outside the project. Returns true only when
     * the user declines. No question is asked with --yes, when the session is not
     * interactive, when Claude Code is not selected, or when there is nothing to
     * install (intelephense already on PATH, or npm missing — which fails loudly later).
     */
    private function declinesGlobalLspInstall(
        InstallationContext $context,
        bool $assumeYes,
        Output $output,
    ): bool {
        if (
            $assumeYes
            || !in_array('claude-code', $context->selectedAgents, true)
            || !$this->confirmationPrompter->isInteractive()
            || $this->commandRunner->isOnPath('intelephense')
            || !$this->commandRunner->isOnPath('npm')
        ) {
            return false;
        }

        $package = IntelephenseEnsurer::PACKAGE;
        $question = "Claude Code's PHP language server needs intelephense. Install $package globally with `npm install -g`?";

        if ($this->confirmationPrompter->confirm($question, default: true)) {
            return false;
        }

        $output->writeLine("Skipping intelephense. Install it later with `npm install -g $package`.");

        return true;
    }

    /**
     * Offer to install the recommended docs search driver when none is present
     * and the session is interactive. Does nothing (falls through gracefully) with
     * --no-interaction, without a terminal (CI), or when a driver is already installed.
     */
    private function maybeInstallDocsDriver(
        Output $output,
        string $projectRoot,
    ): void {
        if ($this->docsDriverResolver->installedDriver($projectRoot) !== null) {
            return;
        }

        $pkg = $this->docsDriverResolver->recommendedUninstalled($projectRoot);

        if ($pkg === null) {
            return;
        }

        if (!$this->confirmationPrompter->isInteractive()) {
            return;
        }

        $question = "No docs search driver installed. Install $pkg to enable search_docs?";
        $confirmed = $this->confirmationPrompter->confirm($question, default: true);

        if (!$confirmed) {
            return;
        }

        if (!$this->commandRunner->isOnPath('composer')) {
            $output->writeLine("composer not found — run `composer require --dev $pkg` to enable search_docs.");

            return;
        }

        $output->writeLine("Installing $pkg via composer (this may take a moment)…");
        $result = $this->commandRunner->run(
            'composer',
            ['require', '--dev', '--no-interaction', '--no-progress', $pkg],
        );

        if ($result['exitCode'] !== 0) {
            $stderr = trim($result['stderr']);
            $hint = $stderr === '' ? '' : " ($stderr)";
            $output->writeLine("composer require --dev $pkg failed$hint — run it manually to enable search_docs.");
        }
    }

    /**
     * Auto-build an installation context from detected agent CLIs on PATH.
     *
     * This is a non-interactive fallback used when --agents wasn't supplied.
     * It announces what it picked and how to override.
     *
     * @param list<string> $detectedAgents
     */
    private function buildContextFromDetection(
        array $detectedAgents,
        bool $force,
        bool $updateGitignore,
        bool $skipLspDeps,
        Output $output,
    ): InstallationContext {
        $output->writeLine(
            $detectedAgents === []
                ? 'No agent CLIs detected on PATH.'
                : 'Detected agents: ' . implode(', ', $detectedAgents),
        );
        $output->writeLine('Pass --agents=<name,name> to override.');

        return new InstallationContext(
            selectedAgents: $detectedAgents,
            force: $force,
            updateGitignore: $updateGitignore,
            skipLspDeps: $skipLspDeps,
        );
    }
}
