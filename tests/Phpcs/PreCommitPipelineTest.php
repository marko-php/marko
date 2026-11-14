<?php

declare(strict_types=1);

use Marko\Tests\Support\Phpcs\PhpcsRunner;
use Marko\Tests\Support\Phpcs\PreCommitRepository;

require_once __DIR__ . '/../Support/Phpcs/PhpcsRunner.php';
require_once __DIR__ . '/../Support/Phpcs/PreCommitRepository.php';

const PRE_COMMIT_FIXTURES = __DIR__ . '/../Fixtures/Phpcs/PreCommit';

/**
 * Stages the combined fixture in a temp repository and runs the real pre-commit hook once.
 *
 * Memoised, because every hook test reads the same run and a run takes a few seconds.
 *
 * `phpcs` and `phpCsFixer` are the CI Lint job's two commands, run over the result, and
 * `secondPassStaged` is what is staged after running the hook a second time.
 *
 * @return array{
 *     hook: array{exitCode: int, output: string},
 *     staged: string,
 *     phpcs: array{exitCode: int, output: string},
 *     phpCsFixer: array{exitCode: int, output: string},
 *     secondPassStaged: string,
 * }
 */
function combinedFixturePreCommitPass(): array
{
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $repository = PreCommitRepository::create();

    try {
        $repository->stage(PRE_COMMIT_FIXTURES . '/Combined.inc');
        $hook = $repository->runHook();
        $staged = $repository->stagedContents();
        $phpcs = $repository->run(['vendor/bin/phpcs', '--standard=phpcs.xml']);
        $phpCsFixer = $repository->run([
            'vendor/bin/php-cs-fixer',
            'fix',
            '--config=.php-cs-fixer.php',
            '--dry-run',
            '--diff',
            '--using-cache=no',
        ]);
        $repository->runHook();

        return $result = [
            'hook' => $hook,
            'staged' => $staged,
            'phpcs' => $phpcs,
            'phpCsFixer' => $phpCsFixer,
            'secondPassStaged' => $repository->stagedContents(),
        ];
    } finally {
        $repository->remove();
    }
}

describe('phpcs.xml', function (): void {
    $sources = fn (): array => array_values(array_unique(array_column(
        new PhpcsRunner()->sniff(PRE_COMMIT_FIXTURES . '/PhpcsXml.inc'),
        'source',
    )));

    it('runs the Marko standard sniffs through phpcs.xml', function () use ($sources): void {
        expect($sources())
            ->toContain('Marko.Formatting.CompoundCondition.OperatorNotOnOwnLine')
            ->toContain('Marko.Formatting.CompoundTernary.ConditionNotParenthesized');
    });

    it('runs RequireMultiLineCondition through phpcs.xml', function () use ($sources): void {
        $sniff = 'SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition';

        expect($sources())->toContain($sniff . '.RequiredMultiLineCondition');
    });

    it('does not lint the phpcs test fixtures as part of phpcs.xml', function (): void {
        $repoRoot = dirname(__DIR__, 2);
        $lintedPaths = array_map(
            'strval',
            iterator_to_array(simplexml_load_file($repoRoot . '/phpcs.xml')->file, false),
        );
        $fixturesPath = substr((string) realpath(__DIR__ . '/../Fixtures/Phpcs'), strlen($repoRoot) + 1);

        expect($lintedPaths)->toBe(['packages'])
            ->and(str_starts_with($fixturesPath, 'packages/'))->toBeFalse();
    });
});

describe('pre-commit hook', function (): void {
    it('fixes the combined fixture to the expected output in one pre-commit pass', function (): void {
        $pass = combinedFixturePreCommitPass();

        expect($pass['staged'])->toBe(
            file_get_contents(PRE_COMMIT_FIXTURES . '/Combined.expected.inc'),
            $pass['hook']['output'],
        );
    });

    it(
        'leaves output that php-cs-fixer dry-run and phpcs both report clean after one pre-commit pass',
        function (): void {
            $pass = combinedFixturePreCommitPass();

            expect($pass['phpCsFixer']['exitCode'])->toBe(0, $pass['phpCsFixer']['output'])
                ->and($pass['phpcs']['exitCode'])->toBe(0, $pass['phpcs']['output']);
        },
    );

    it('leaves the combined fixture unchanged on a second pre-commit pass', function (): void {
        $pass = combinedFixturePreCommitPass();

        expect($pass['secondPassStaged'])->toBe($pass['staged']);
    });

    it('keeps the parentheses added around a compound ternary condition', function (): void {
        $staged = combinedFixturePreCommitPass()['staged'];

        expect($staged)
            ->toContain('$title = ($name === null || $name === \'\')')
            ->toContain('values: ($isNew || $isRequired)');
    });
});

describe('composer cs:fix', function (): void {
    it('runs php-cs-fixer again after phpcbf in composer cs:fix', function (): void {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        expect($composer['scripts']['cs:fix'])->toBe([
            'php-cs-fixer fix --config=.php-cs-fixer.php',
            'phpcbf --standard=phpcs.xml || true',
            'php-cs-fixer fix --config=.php-cs-fixer.php',
        ]);
    });
});
