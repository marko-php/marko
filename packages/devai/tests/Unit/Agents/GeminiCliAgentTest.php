<?php

declare(strict_types=1);

use Marko\DevAi\Agents\GeminiCliAgent;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;

function makeGeminiRunner(bool $installed = false): CommandRunnerInterface
{
    return new class ($installed) implements CommandRunnerInterface
    {
        /** @var list<array{command: string, args: list<string>}> */
        public array $calls = [];

        public function __construct(private bool $installed) {}

        public function run(string $command, array $args = []): array
        {
            $this->calls[] = ['command' => $command, 'args' => $args];

            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        public function isOnPath(string $binary): bool
        {
            return $this->installed;
        }
    };
}

it('reports name as gemini-cli', function () {
    $agent = new GeminiCliAgent(makeGeminiRunner());
    expect($agent->name())->toBe('gemini-cli');
});

it('detects installation when gemini binary is on PATH', function () {
    $agent = new GeminiCliAgent(makeGeminiRunner(installed: true));
    expect($agent->isInstalled())->toBeTrue();

    $agent2 = new GeminiCliAgent(makeGeminiRunner(installed: false));
    expect($agent2->isInstalled())->toBeFalse();
});

it('writes GEMINI.md with Marko guidelines', function () {
    $dir = sys_get_temp_dir() . '/gemini-test-' . uniqid();
    mkdir($dir);

    $agent = new GeminiCliAgent(makeGeminiRunner());
    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $dir);

    expect(file_get_contents($dir . '/GEMINI.md'))->toBe('# Marko Guidelines');

    unlink($dir . '/GEMINI.md');
    unlink($dir . '/AGENTS.md');
    rmdir($dir);
});

it('ensures AGENTS.md is present', function () {
    $dir = sys_get_temp_dir() . '/gemini-test-' . uniqid();
    mkdir($dir);

    $agent = new GeminiCliAgent(makeGeminiRunner());
    $content = new GuidelinesContent('# Marko Guidelines');
    $agent->writeGuidelines($content, $dir);

    expect(file_exists($dir . '/AGENTS.md'))->toBeTrue();

    // Should not overwrite existing AGENTS.md
    file_put_contents($dir . '/AGENTS.md', 'existing');
    $agent->writeGuidelines(new GuidelinesContent('# New'), $dir);
    expect(file_get_contents($dir . '/AGENTS.md'))->toBe('existing');

    unlink($dir . '/GEMINI.md');
    unlink($dir . '/AGENTS.md');
    rmdir($dir);
});

it('registers marko-mcp via gemini mcp add command', function () {
    $runner = makeGeminiRunner();
    $agent = new GeminiCliAgent($runner);
    $reg = new McpRegistration('marko-mcp', 'npx', ['-y', '@marko/mcp']);
    $agent->registerMcpServer($reg, '/tmp');

    expect($runner->calls)->toHaveCount(1);
    expect($runner->calls[0]['command'])->toBe('gemini');
    expect($runner->calls[0]['args'])->toBe(['mcp', 'add', '-s', 'project', '-t', 'stdio', 'marko-mcp', 'npx', '-y', '@marko/mcp']);
});

it('supports Guidelines Mcp Skills capabilities', function () {
    $agent = new GeminiCliAgent(makeGeminiRunner());
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class);
    expect($agent)->toBeInstanceOf(SupportsMcp::class);
    expect($agent)->toBeInstanceOf(SupportsSkills::class);
});
