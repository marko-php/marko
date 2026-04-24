<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Marko\DevAi\Agents\ClaudeCodeAgent;
use Marko\DevAi\Agents\CodexAgent;
use Marko\DevAi\Agents\CopilotAgent;
use Marko\DevAi\Agents\CursorAgent;
use Marko\DevAi\Agents\GeminiCliAgent;
use Marko\DevAi\Agents\JunieAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Process\CommandRunnerInterface;

class AgentRegistry
{
    public function __construct(
        private readonly CommandRunnerInterface $runner,
    ) {}

    /** @return array<string, AgentInterface> name => agent */
    public function all(string $projectRoot): array
    {
        return [
            'claude-code' => new ClaudeCodeAgent($this->runner),
            'codex' => new CodexAgent($this->runner),
            'cursor' => new CursorAgent($this->runner),
            'copilot' => new CopilotAgent($projectRoot),
            'gemini-cli' => new GeminiCliAgent($this->runner),
            'junie' => new JunieAgent($projectRoot),
        ];
    }
}
