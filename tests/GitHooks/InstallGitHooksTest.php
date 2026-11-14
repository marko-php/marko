<?php

declare(strict_types=1);

use Marko\Tests\Support\GitHooks\HookInstallerSandbox;

require_once __DIR__ . '/../Support/GitHooks/HookInstallerSandbox.php';

describe('install-git-hooks', function (): void {
    beforeEach(function (): void {
        $this->sandbox = HookInstallerSandbox::create();
    });

    afterEach(function (): void {
        $this->sandbox->remove();
    });

    it('sets core.hooksPath to .githooks in a git checkout', function (): void {
        $repository = $this->sandbox->initRepository('project');

        $result = $this->sandbox->runInstaller($repository);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBe('.githooks');
    });

    it('leaves an existing matching core.hooksPath unchanged', function (): void {
        $repository = $this->sandbox->initRepository('project');
        $this->sandbox->setHooksPath($repository, '.githooks');

        $result = $this->sandbox->runInstaller($repository);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBe('.githooks');
    });

    it('warns and leaves a different existing core.hooksPath unchanged', function (): void {
        $repository = $this->sandbox->initRepository('project');
        $this->sandbox->setHooksPath($repository, 'custom-hooks');

        $result = $this->sandbox->runInstaller($repository);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and(str_contains($result['output'], "core.hooksPath is already set to 'custom-hooks'"))
            ->toBeTrue($result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBe('custom-hooks');
    });

    it("replaces a core.hooksPath that points at git's default hooks directory", function (bool $absolute): void {
        $repository = $this->sandbox->initRepository('project');
        $this->sandbox->setHooksPath($repository, $absolute ? $repository . '/.git/hooks' : '.git/hooks');

        $result = $this->sandbox->runInstaller($repository);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBe('.githooks');
    })->with([
        'absolute' => [true],
        'relative' => [false],
    ]);

    it('exits successfully without changes outside a git checkout', function (): void {
        $this->sandbox->addPreCommitHook('project');

        $result = $this->sandbox->runInstaller($this->sandbox->path('project'));

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and(str_contains($result['output'], 'is not a git checkout'))->toBeTrue($result['output'])
            ->and(file_exists($this->sandbox->path('project/.git')))->toBeFalse();
    });

    it(
        'exits successfully without changes when the project sits inside an unrelated git repository',
        function (): void {
            $outerRepository = $this->sandbox->initRepository('outer', withPreCommitHook: false);
            $this->sandbox->addPreCommitHook('outer/project');

            $result = $this->sandbox->runInstaller($this->sandbox->path('outer/project'));

            expect($result['exitCode'])->toBe(0, $result['output'])
                ->and(str_contains($result['output'], 'is not a git checkout'))->toBeTrue($result['output'])
                ->and($this->sandbox->hooksPath($outerRepository))->toBeNull();
        },
    );

    it('exits successfully with a warning when git cannot run', function (): void {
        $repository = $this->sandbox->initRepository('project');

        $result = $this->sandbox->runInstaller($repository, ['PATH' => $this->sandbox->path('no-git')]);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and(str_contains($result['output'], 'Warning: git could not run'))->toBeTrue($result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBeNull();
    });

    it('exits successfully without changes when the pre-commit hook is missing', function (): void {
        $repository = $this->sandbox->initRepository('project', withPreCommitHook: false);

        $result = $this->sandbox->runInstaller($repository);

        expect($result['exitCode'])->toBe(0, $result['output'])
            ->and(str_contains($result['output'], 'has no .githooks/pre-commit'))->toBeTrue($result['output'])
            ->and($this->sandbox->hooksPath($repository))->toBeNull();
    });
});

describe('composer scripts', function (): void {
    it('runs the hook installer from post-install-cmd', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        expect($composer['scripts'])->toHaveKey('post-install-cmd')
            ->and((array) $composer['scripts']['post-install-cmd'])->toContain('@php tools/install-git-hooks.php');
    });

    it('runs the hook installer from post-update-cmd', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        expect($composer['scripts'])->toHaveKey('post-update-cmd')
            ->and((array) $composer['scripts']['post-update-cmd'])->toContain('@php tools/install-git-hooks.php');
    });
});
