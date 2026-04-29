<?php

declare(strict_types=1);

use Marko\DevAi\Agents\AbstractAgent;
use Marko\DevAi\Contract\AgentInterface;
use Marko\DevAi\Contract\SupportsGuidelines;
use Marko\DevAi\Contract\SupportsLsp;
use Marko\DevAi\Contract\SupportsMcp;
use Marko\DevAi\Contract\SupportsSettings;
use Marko\DevAi\Contract\SupportsSkills;
use Marko\DevAi\ValueObject\GuidelinesContent;
use Marko\DevAi\ValueObject\McpRegistration;
use Marko\DevAi\ValueObject\SkillBundle;

it('defines AgentInterface with name and capability-detection methods', function (): void {
    $r = new ReflectionClass(AgentInterface::class);
    expect($r->isInterface())->toBeTrue()
        ->and($r->hasMethod('name'))->toBeTrue()
        ->and($r->hasMethod('displayName'))->toBeTrue()
        ->and($r->hasMethod('isInstalled'))->toBeTrue();
});

it('defines SupportsGuidelines SupportsMcp SupportsSettings SupportsSkills capability interfaces', function (): void {
    foreach ([SupportsGuidelines::class, SupportsMcp::class, SupportsSettings::class, SupportsSkills::class] as $iface) {
        expect((new ReflectionClass($iface))->isInterface())->toBeTrue();
    }
});

it('SupportsLsp interface no longer exists (removed — Claude Code uses plugin distribution now)', function (): void {
    expect(interface_exists(SupportsLsp::class))->toBeFalse();
});

it('provides AbstractAgent base class with default no-op implementations', function (): void {
    $r = new ReflectionClass(AbstractAgent::class);
    expect($r->isAbstract())->toBeTrue()
        ->and($r->implementsInterface(AgentInterface::class))->toBeTrue();
});

it('allows adapters to opt into each capability independently', function (): void {
    $agent = new class () extends AbstractAgent implements SupportsGuidelines
    {
        public function name(): string
        {
            return 'test';
        }

        public function displayName(): string
        {
            return 'Test';
        }

        public function writeGuidelines(GuidelinesContent $c, string $root): void {}
    };
    expect($agent)->toBeInstanceOf(SupportsGuidelines::class)
        ->and($agent)->not->toBeInstanceOf(SupportsMcp::class);
});

it('includes readonly value objects for GuidelinesContent McpRegistration SkillBundle', function (): void {
    foreach ([GuidelinesContent::class, McpRegistration::class, SkillBundle::class] as $vo) {
        expect((new ReflectionClass($vo))->isReadOnly())->toBeTrue();
    }
});
