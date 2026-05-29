<?php

declare(strict_types=1);

use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

it('defines AgentInterface with identity and a single install() method', function (): void {
    $r = new ReflectionClass(AgentInterface::class);
    expect($r->isInterface())->toBeTrue()
        ->and($r->hasMethod('name'))->toBeTrue()
        ->and($r->hasMethod('displayName'))->toBeTrue()
        ->and($r->hasMethod('isInstalled'))->toBeTrue()
        ->and($r->hasMethod('install'))->toBeTrue();
});

it('install() takes an InstallationContext and a project root (force lives on the context)', function (): void {
    $params = (new ReflectionMethod(AgentInterface::class, 'install'))->getParameters();
    $names = array_map(fn ($p) => $p->getName(), $params);

    expect($names)->toBe(['ctx', 'projectRoot'])
        ->and((string) $params[0]->getType())->toBe('Marko\DevAi\Installation\InstallationContext');
});

it('removed the per-capability marker interfaces (subtraction — agents own their install)', function (): void {
    foreach ([
        'Marko\DevAi\Contract\SupportsGuidelines',
        'Marko\DevAi\Contract\SupportsMcp',
        'Marko\DevAi\Contract\SupportsSettings',
        'Marko\DevAi\Contract\SupportsSkills',
    ] as $iface) {
        expect(interface_exists($iface))->toBeFalse();
    }
});

it('removed the empty AbstractAgent base class', function (): void {
    expect(class_exists('Marko\DevAi\Agents\AbstractAgent'))->toBeFalse();
});

it('includes readonly value objects for GuidelinesContent, McpRegistration, SkillBundle', function (): void {
    foreach ([GuidelinesContent::class, McpRegistration::class, SkillBundle::class] as $vo) {
        expect((new ReflectionClass($vo))->isReadOnly())->toBeTrue();
    }
});
