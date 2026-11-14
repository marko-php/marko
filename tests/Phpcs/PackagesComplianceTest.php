<?php

declare(strict_types=1);

/**
 * @return array<int, string>
 */
function compoundRuleViolationsInPackages(
    string $sniffs,
): array {
    $root = dirname(__DIR__, 2);
    $command = sprintf(
        '%s --standard=%s --sniffs=%s --parallel=8 --report=json %s 2>/dev/null',
        escapeshellarg($root . '/vendor/bin/phpcs'),
        escapeshellarg($root . '/phpcs.xml'),
        escapeshellarg($sniffs),
        escapeshellarg($root . '/packages'),
    );
    $report = json_decode((string) shell_exec($command), true, flags: JSON_THROW_ON_ERROR);
    $violations = [];

    foreach ($report['files'] as $file => $result) {
        foreach ($result['messages'] as $message) {
            $violations[] = $file . ':' . $message['line'] . ' ' . $message['source'];
        }
    }

    return $violations;
}

describe('packages compliance with rule 16', function (): void {
    it('reports no compound condition violations anywhere in packages', function (): void {
        expect(compoundRuleViolationsInPackages(
            'SlevomatCodingStandard.ControlStructures.RequireMultiLineCondition,Marko.Formatting.CompoundCondition',
        ))->toBe([]);
    });

    it('reports no compound ternary violations anywhere in packages', function (): void {
        expect(compoundRuleViolationsInPackages('Marko.Formatting.CompoundTernary'))->toBe([]);
    });
});
