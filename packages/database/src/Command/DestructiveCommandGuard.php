<?php

declare(strict_types=1);

namespace Marko\Database\Command;

use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Environment\AppEnvironment;

/**
 * Decides whether a destructive database command (db:rebuild, db:reset, db:rollback, db:seed)
 * may run in the current environment.
 *
 * Destructive commands need evidence that the database is disposable, not merely the absence
 * of the word "production":
 *
 * - development and testing: allowed
 * - production (or no environment set): refused, even with --force
 * - anything else (staging, qa, a typo, ...): refused unless --force is passed; with --force,
 *   the command still asks for confirmation when someone can answer
 *
 * A command whose change production also needs (such as pruning stale admin permissions) opts in
 * per call with allowInProduction: production is then treated like staging, so it still needs
 * --force and still asks when someone can answer. confirmInDevelopment makes development and
 * testing ask for confirmation too when someone can answer.
 *
 * --force is the terminal confirmation only. A command that calls this guard must also be marked
 * #[Command(destructive: true)]: callers that run commands for someone else (the MCP run_console_command
 * tool) read that marker, not the presence of a force flag, and a repository test fails when a
 * shipped command that depends on this guard is unmarked.
 */
readonly class DestructiveCommandGuard
{
    public function __construct(
        private AppEnvironment $appEnvironment,
        private ConfirmationPrompterInterface $confirmationPrompter,
    ) {}

    /**
     * Returns null when the command may go ahead, or the exit code to stop with:
     * 1 when the environment refuses it, 0 when the user declines the confirmation.
     *
     * @param string $command The command name, e.g. "db:rebuild"
     * @param string $effect What the command does to the database, e.g. "drops every table"
     * @param bool $allowInProduction Let production run the command with --force instead of refusing it outright
     * @param bool $confirmInDevelopment Ask for confirmation in development and testing when someone can answer
     */
    public function check(
        string $command,
        string $effect,
        Input $input,
        Output $output,
        bool $allowInProduction = false,
        bool $confirmInDevelopment = false,
    ): ?int {
        $environment = $this->appEnvironment->name();

        if (
            $this->appEnvironment->isProduction()
            && !$allowInProduction
        ) {
            $output->writeLine("Error: $command cannot be run in the '$environment' environment.");
            $output->writeLine("This command $effect and is never allowed in production, even with --force.");

            return 1;
        }

        if (
            $this->appEnvironment->isDevelopment()
            || $this->appEnvironment->isTesting()
        ) {
            if (!$confirmInDevelopment) {
                return null;
            }

            return $this->confirm($command, $effect, $environment, $output);
        }

        if (!$input->hasOption('force')) {
            $output->writeLine("Error: $command is refused in the '$environment' environment without --force.");
            $output->writeLine(
                "This command $effect. It runs without --force only in development "
                . '(development, dev, local) and testing (testing, test).',
            );
            $output->writeLine("Re-run with --force if the '$environment' database may be changed.");

            return 1;
        }

        return $this->confirm($command, $effect, $environment, $output);
    }

    /**
     * Ask when someone can answer; go ahead (null) when nobody can, or 0 when the answer is no.
     */
    private function confirm(
        string $command,
        string $effect,
        string $environment,
        Output $output,
    ): ?int {
        if (!$this->confirmationPrompter->isInteractive()) {
            return null;
        }

        if ($this->confirmationPrompter->confirm("$command $effect in the '$environment' environment. Continue?")) {
            return null;
        }

        $output->writeLine("$command cancelled.");

        return 0;
    }
}
