<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use DateTimeInterface;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Guidelines\GuidelinesAggregator;
use Marko\DevAi\Rendering\AgentsMdRenderer;
use Marko\DevAi\Rendering\ClaudeMdRenderer;
use Marko\DevAi\Skills\SkillsDistributor;
use Marko\DevAi\ValueObject\LspRegistration;
use Marko\DevAi\ValueObject\McpRegistration;

class InstallationOrchestrator
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(
        private AgentRegistry $registry,
        private AgentsMdRenderer $agentsRenderer,
        private ClaudeMdRenderer $claudeRenderer,
        private GuidelinesAggregator $guidelinesAggregator,
        private SkillsDistributor $skillsDistributor,
    ) {}

    /** @return array{status: string, message?: string, log?: list<string>} */
    public function install(InstallationContext $ctx, string $projectRoot, bool $force): array
    {
        $marker = $projectRoot . '/.marko/devai.json';
        if (is_file($marker) && !$force) {
            return [
                'status' => 'skipped',
                'message' => 'Prior install detected at .marko/devai.json. Use `marko devai:update` to update, or pass --force to re-run.',
            ];
        }

        $guidelines = $this->guidelinesAggregator->aggregate();
        $agentsMd = $this->agentsRenderer->render([
            'projectName' => basename($projectRoot),
            'guidelines' => $guidelines,
        ]);

        $skills = $this->skillsDistributor->collect();

        $agents = $this->registry->all($projectRoot);
        $mcp = new McpRegistration(serverName: 'marko-mcp', command: 'php', args: ['marko', 'mcp:serve']);
        $lsp = new LspRegistration(serverName: 'marko-lsp', command: 'php', args: ['marko', 'lsp:serve']);

        foreach ($ctx->selectedAgents as $agentName) {
            if (!isset($agents[$agentName])) {
                continue;
            }
            $agent = $agents[$agentName];

            if ($agent instanceof SupportsGuidelines) {
                $agent->writeGuidelines($agentsMd, $projectRoot);
                $this->log[] = "[$agentName] wrote guidelines";
            }
            if ($agent instanceof SupportsMcp) {
                $agent->registerMcpServer($mcp, $projectRoot);
                $this->log[] = "[$agentName] registered MCP server";
            }
            if ($agent instanceof SupportsLsp) {
                $agent->registerLspServer($lsp, $projectRoot);
                $this->log[] = "[$agentName] registered LSP server";
            }
            if ($agent instanceof SupportsSkills) {
                $agent->distributeSkills($skills, $projectRoot);
                $this->log[] = "[$agentName] distributed " . count($skills) . ' skills';
            }
        }

        $markerDir = $projectRoot . '/.marko';
        if (!is_dir($markerDir)) {
            mkdir($markerDir, 0755, true);
        }
        file_put_contents($marker, json_encode([
            'agents' => $ctx->selectedAgents,
            'docsDriver' => $ctx->docsDriver,
            'installedAt' => date(DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT));

        if ($ctx->updateGitignore) {
            $this->updateGitignore($projectRoot);
        }

        return ['status' => 'installed', 'log' => $this->log];
    }

    private function updateGitignore(string $projectRoot): void
    {
        $path = $projectRoot . '/.gitignore';
        $existing = is_file($path) ? (string) file_get_contents($path) : '';
        $lines = ['# marko/devai generated files', '.marko/'];
        $additions = '';
        foreach ($lines as $l) {
            if (str_contains($existing, $l)) {
                continue;
            }
            $additions .= $l . "\n";
        }
        if ($additions !== '') {
            $separator = ($existing !== '' && !str_ends_with($existing, "\n")) ? "\n" : '';
            file_put_contents($path, $existing . $separator . $additions);
        }
    }
}
