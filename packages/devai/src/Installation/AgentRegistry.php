<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Composer\InstalledVersions;
use Marko\Config\ConfigRepositoryInterface;
use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Agents\CodexAgent;
use Marko\DevAi\Agents\CopilotAgent;
use Marko\DevAi\Agents\CursorAgent;
use Marko\DevAi\Agents\GeminiCliAgent;
use Marko\DevAi\Agents\JunieAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Exceptions\DevAiInstallException;
use Marko\DevAi\Process\CommandRunnerInterface;

class AgentRegistry
{
    private const string MARKETPLACE_REF_KEY = 'devai.claude_code.marketplace_ref';

    public function __construct(
        private readonly CommandRunnerInterface $runner,
        private readonly ?ConfigRepositoryInterface $config = null,
    ) {}

    /**
     * @return array<string, AgentInterface> name => agent
     * @throws DevAiInstallException when devai.claude_code.marketplace_ref is set to something other than a non-empty string
     */
    public function all(
        string $projectRoot,
    ): array {
        return [
            'claude-code' => new ClaudeCodeAgent(
                $this->runner,
                new IntelephenseEnsurer($this->runner),
                $this->marketplaceRef(),
            ),
            'codex' => new CodexAgent($this->runner),
            'cursor' => new CursorAgent($this->runner),
            'copilot' => new CopilotAgent($projectRoot),
            'gemini-cli' => new GeminiCliAgent($this->runner),
            'junie' => new JunieAgent($projectRoot),
        ];
    }

    /**
     * The git ref the Claude Code plugin marketplace is pinned to: the configured
     * devai.claude_code.marketplace_ref when set, otherwise the installed marko/devai
     * release (so plugins match the framework version), otherwise the pinned default.
     *
     * @throws DevAiInstallException
     */
    private function marketplaceRef(): string
    {
        $configured = $this->config?->has(self::MARKETPLACE_REF_KEY) === true
            ? $this->config->get(self::MARKETPLACE_REF_KEY)
            : null;

        if ($configured !== null) {
            if (
                !is_string($configured)
                || trim($configured) === ''
            ) {
                throw DevAiInstallException::invalidConfig(
                    self::MARKETPLACE_REF_KEY,
                    'a non-empty git tag or commit',
                    $configured,
                );
            }

            return trim($configured);
        }

        $installed = InstalledVersions::isInstalled('marko/devai')
            ? InstalledVersions::getPrettyVersion('marko/devai')
            : null;

        if (
            $installed !== null
            && preg_match('/^v?\d+\.\d+\.\d+$/', $installed) === 1
        ) {
            return $installed;
        }

        return ClaudeCodeAgent::DEFAULT_MARKETPLACE_REF;
    }
}
