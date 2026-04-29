<?php

declare(strict_types=1);

use Marko\DevAi\Agents\CodexAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

function makeRunner(): CommandRunnerInterface
{
    return new class () implements CommandRunnerInterface
    {
        /** @var list<array{command: string, args: list<string>}> */
        public array $calls = [];

        public bool $onPath = false;

        public function run(string $command, array $args = []): array
        {
            $this->calls[] = ['command' => $command, 'args' => $args];

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $this->onPath;
        }
    };
}

it('reports name as codex', function (): void {
    $agent = new CodexAgent(makeRunner());
    expect($agent->name())->toBe('codex');
});

it('detects installation when codex binary is on PATH', function (): void {
    $runner = makeRunner();
    $runner->onPath = true;
    $agent = new CodexAgent($runner);
    expect($agent->isInstalled())->toBeTrue();

    $runner2 = makeRunner();
    $runner2->onPath = false;
    $agent2 = new CodexAgent($runner2);
    expect($agent2->isInstalled())->toBeFalse();
});

it('writes canonical AGENTS.md with Marko guidelines', function (): void {
    $agent = new CodexAgent(makeRunner());
    $root = sys_get_temp_dir() . '/codex-test-' . uniqid();
    mkdir($root, 0755, true);

    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $root);

    expect(file_get_contents($root . '/AGENTS.md'))->toBe('# Marko Guidelines');

    // cleanup
    unlink($root . '/AGENTS.md');
    rmdir($root);
});

it('registers marko-mcp via codex mcp add command with correct argument separator', function (): void {
    $runner = makeRunner();
    $agent = new CodexAgent($runner);

    $reg = new McpRegistration('marko-mcp', 'php', ['marko', 'mcp:serve']);
    $agent->registerMcpServer($reg, '/project');

    expect($runner->calls)->toHaveCount(1);
    $call = $runner->calls[0];
    expect($call['command'])->toBe('codex');

    $args = $call['args'];
    expect($args[0])->toBe('mcp')
        ->and($args[1])->toBe('add')
        ->and($args[2])->toBe('marko-mcp')
        ->and($args[3])->toBe('--')
        ->and($args[4])->toBe('php')
        ->and($args[5])->toBe('marko')
        ->and($args[6])->toBe('mcp:serve');
});

it('distributes skills to .agents/skills directory', function (): void {
    $agent = new CodexAgent(makeRunner());
    $root = sys_get_temp_dir() . '/codex-skills-' . uniqid();
    mkdir($root, 0755, true);

    $bundle = new SkillBundle('marko', ['plan-create.md' => '# Plan Create', 'plan-orchestrate.md' => '# Plan Orchestrate']);
    $agent->distributeSkills([$bundle], $root);

    $skillsDir = $root . '/.agents/skills';
    expect(is_dir($skillsDir))->toBeTrue()
        ->and(file_get_contents($skillsDir . '/plan-create.md'))->toBe('# Plan Create')
        ->and(file_get_contents($skillsDir . '/plan-orchestrate.md'))->toBe('# Plan Orchestrate');

    // cleanup
    unlink($skillsDir . '/plan-create.md');
    unlink($skillsDir . '/plan-orchestrate.md');
    rmdir($skillsDir);
    rmdir($root . '/.agents');
    rmdir($root);
});

it('supports Guidelines Mcp Skills capabilities', function (): void {
    $agent = new CodexAgent(makeRunner());
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class)
        ->and($agent)->toBeInstanceOf(SupportsMcp::class)
        ->and($agent)->toBeInstanceOf(SupportsSkills::class);
});
