<?php

declare(strict_types=1);

use Marko\CodeIndexer\Module\ModuleWalker;
use Marko\CodeIndexer\ValueObject\ModuleInfo;
use Marko\DevAi\Installation\InstallationContext;
use Marko\DevAi\Process\CommandRunnerInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

// ---------------------------------------------------------------------------
// Shared devai test helpers (loaded via composer autoload-dev.files)
// ---------------------------------------------------------------------------

if (!function_exists('devaiTempDir')) {
    /** Create a unique temp directory for an install fixture. */
    function devaiTempDir(): string
    {
        $dir = sys_get_temp_dir() . '/devai-test-' . uniqid('', true);
        mkdir($dir, 0755, true);

        return $dir;
    }

    /** Recursively remove a temp directory created by devaiTempDir(). */
    function devaiRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iter as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    /**
     * A flexible CommandRunner double. Records every call; reports binaries
     * on/off PATH; returns a configurable stdout for `<cmd> mcp list`.
     */
    function devaiRunner(bool $onPath = false, string $listOutput = ''): CommandRunnerInterface
    {
        return new class ($onPath, $listOutput) implements CommandRunnerInterface
        {
            /** @var list<array{command: string, args: list<string>}> */
            public array $calls = [];

            public function __construct(
                public bool $onPath,
                public string $listOutput,
            ) {}

            public function run(
                string $command,
                array $args = [],
            ): array {
                $this->calls[] = ['command' => $command, 'args' => $args];
                if (($args[0] ?? '') === 'mcp' && ($args[1] ?? '') === 'list') {
                    return ['exitCode' => 0, 'stdout' => $this->listOutput, 'stderr' => ''];
                }

                return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
            }

            public function isOnPath(string $binary): bool
            {
                return $this->onPath;
            }
        };
    }

    /**
     * Build an InstallationContext already enriched with the shared install data
     * the orchestrator normally computes — ready to hand straight to an agent's
     * install().
     *
     * @param list<SkillBundle> $skills
     * @param list<string> $previouslyShipped
     */
    function devaiContext(
        string $guidelinesBody = '# Marko Guidelines',
        bool $force = false,
        bool $skipLspDeps = false,
        array $skills = [],
        array $previouslyShipped = [],
        ?McpRegistration $mcp = null,
    ): InstallationContext {
        return new InstallationContext(
            selectedAgents: [],
            force: $force,
            skipLspDeps: $skipLspDeps,
            guidelines: new GuidelinesContent($guidelinesBody),
            skills: $skills,
            previouslyShipped: $previouslyShipped,
            mcpRegistration: $mcp ?? new McpRegistration('marko-mcp', '/project/vendor/bin/marko', ['mcp:serve']),
        );
    }

    /**
     * A ModuleWalker double returning a fixed module list. The codeindexer
     * ModuleWalkerInterface was removed (#97); doubles now extend the concrete
     * ModuleWalker and override walk().
     *
     * @param list<ModuleInfo> $modules
     */
    function devaiWalker(array $modules = []): ModuleWalker
    {
        return new class ($modules) extends ModuleWalker
        {
            /** @param list<ModuleInfo> $modules */
            public function __construct(private array $modules) {}

            public function walk(): array
            {
                return $this->modules;
            }
        };
    }
}
