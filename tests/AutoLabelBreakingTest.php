<?php

declare(strict_types=1);

$script = dirname(__DIR__) . '/.github/scripts/is-breaking-pr.sh';

/**
 * Runs the auto-label breaking-change detector the way the workflow does, with the PR title
 * and body in the environment, and returns its single-word answer.
 */
function isBreakingPr(string $script, string $title, string $body): string
{
    $command = 'PR_TITLE=' . escapeshellarg($title) . ' PR_BODY=' . escapeshellarg($body)
        . ' bash ' . escapeshellarg($script);

    return trim((string) shell_exec($command));
}

it('treats a ! after the type as breaking', function (string $title) use ($script): void {
    expect(isBreakingPr($script, $title, ''))->toBe('true');
})->with([
    'feat!:' => 'feat!: rename the queue contract',
    'scoped refactor!:' => 'refactor(cache)!: require ProjectPaths',
    'uppercase type' => 'Fix!: change a default',
]);

it('treats a conventional BREAKING CHANGE footer as breaking', function (string $footer) use ($script): void {
    $body = "## Summary\n\nChanges the job contract.\n\n$footer the release() signature changed.";

    expect(isBreakingPr($script, 'feat: job contract', $body))->toBe('true');
})->with([
    'BREAKING CHANGE:' => 'BREAKING CHANGE:',
    'BREAKING-CHANGE:' => 'BREAKING-CHANGE:',
]);

it('does not treat a "Breaking changes: None" section as breaking', function (string $body) use ($script): void {
    expect(isBreakingPr($script, 'feat: add a rule', $body))->toBe('false');
})->with([
    'heading with None' => "## Summary\n\nAdds a rule.\n\n## Breaking changes\n\nNone.",
    'inline mention' => 'This is not a breaking change for anyone.',
    'lowercase footer' => "Summary.\n\nbreaking change: none",
    'footer word mid-line' => 'There is no BREAKING CHANGE: here, just prose.',
]);

it('treats a plain title with an empty body as not breaking', function () use ($script): void {
    expect(isBreakingPr($script, 'fix: correct a typo', ''))->toBe('false');
});

it('runs the detector script from the auto-label workflow', function (): void {
    $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/auto-label.yml');

    expect($workflow)
        ->toContain('.github/scripts/is-breaking-pr.sh')
        ->not->toContain("grep -qiF 'BREAKING CHANGE'");
});

it('fails the auto-label check when the PR title is not a conventional commit', function (): void {
    $workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/auto-label.yml');

    expect($workflow)
        ->toContain('PR title is not a conventional commit')
        ->toContain("Unknown PR title type '\$TYPE'");
});
