<?php

declare(strict_types=1);

use Marko\Core\Attributes\Subject;
use Marko\Core\Container\Container;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// All fixture classes are prefixed with PBS_ to avoid conflicts.

interface PBS_GreeterInterface
{
    public function greet(
        string $name,
    ): string;
}

class PBS_Greeter implements PBS_GreeterInterface
{
    public function greet(
        string $name,
    ): string {
        return 'hello ' . $name;
    }
}

class PBS_Counter
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function report(): string
    {
        return 'reported';
    }
}

class PBS_Shouter
{
    public function shout(
        string $text,
    ): string {
        return $text . '!';
    }
}

class PBS_SubjectRecorderPlugin
{
    public static mixed $subject = null;

    public static ?int $countSeen = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function greet(
        #[Subject]
        mixed $subject = null,
    ): null {
        self::$subject = $subject;

        return null;
    }

    /** @noinspection PhpUnused - Invoked via reflection */
    public function report(
        #[Subject]
        mixed $subject = null,
    ): null {
        self::$subject = $subject;
        self::$countSeen = $subject->count ?? null;

        return null;
    }
}

class PBS_UppercasePlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function shout(
        #[Subject]
        mixed $subject = null,
        mixed ...$arguments,
    ): array {
        return [strtoupper((string) ($arguments[0] ?? 'subject-was-not-first'))];
    }
}

class PBS_PlainArgumentRecorderPlugin
{
    public static ?string $received = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function shout(
        string $text,
    ): null {
        self::$received = $text;

        return null;
    }
}

class PBS_ShortCircuitPlugin
{
    /** @noinspection PhpUnused - Invoked via reflection */
    public function greet(
        #[Subject]
        mixed $subject = null,
        mixed ...$arguments,
    ): string {
        return $subject instanceof PBS_GreeterInterface
            ? 'short-circuited for ' . $arguments[0]
            : 'subject-was-not-first';
    }
}

class PBS_ShortCircuitAfterRecorder
{
    public static bool $ran = false;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function greet(
        mixed $result,
    ): mixed {
        self::$ran = true;

        return $result;
    }
}

function makePbsInterceptor(
    PluginRegistry $registry,
): PluginInterceptor {
    return new PluginInterceptor(new Container(), $registry, new InterceptorClassGenerator());
}

beforeEach(function (): void {
    PBS_SubjectRecorderPlugin::$subject = null;
    PBS_SubjectRecorderPlugin::$countSeen = null;
    PBS_PlainArgumentRecorderPlugin::$received = null;
    PBS_ShortCircuitAfterRecorder::$ran = false;
});

it('passes the resolved interface wrapper to a before plugin Subject parameter', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PBS_SubjectRecorderPlugin::class,
        targetClass: PBS_GreeterInterface::class,
        beforeMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10, 'subject' => true]],
    ));

    $resolved = makePbsInterceptor($registry)->createProxy(
        PBS_GreeterInterface::class,
        PBS_Greeter::class,
        new PBS_Greeter(),
    );
    $result = $resolved->greet('Ada');

    expect(PBS_SubjectRecorderPlugin::$subject)->toBe($resolved)
        ->and($result)->toBe('hello Ada');
});

it('passes the resolved concrete subclass to a before plugin Subject parameter', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PBS_SubjectRecorderPlugin::class,
        targetClass: PBS_Counter::class,
        beforeMethods: ['report' => ['pluginMethod' => 'report', 'sortOrder' => 10, 'subject' => true]],
    ));

    $resolved = makePbsInterceptor($registry)->createProxy(
        PBS_Counter::class,
        PBS_Counter::class,
        new PBS_Counter(),
    );
    $result = $resolved->report();

    expect(PBS_SubjectRecorderPlugin::$subject)->toBe($resolved)
        ->and($result)->toBe('reported');
});

it(
    'gives a before plugin a subject that shows state changed by earlier calls on a concrete target',
    function (): void {
        $registry = new PluginRegistry();
        $registry->register(new PluginDefinition(
            pluginClass: PBS_SubjectRecorderPlugin::class,
            targetClass: PBS_Counter::class,
            beforeMethods: ['report' => ['pluginMethod' => 'report', 'sortOrder' => 10, 'subject' => true]],
        ));

        $resolved = makePbsInterceptor($registry)->createProxy(
            PBS_Counter::class,
            PBS_Counter::class,
            new PBS_Counter(),
        );
        $resolved->increment();
        $resolved->increment();
        $resolved->report();

        expect(PBS_SubjectRecorderPlugin::$countSeen)->toBe(2);
    },
);

it('applies modified arguments from a before plugin that also takes the subject', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PBS_UppercasePlugin::class,
        targetClass: PBS_Shouter::class,
        beforeMethods: ['shout' => ['pluginMethod' => 'shout', 'sortOrder' => 10, 'subject' => true]],
    ));
    $registry->register(new PluginDefinition(
        pluginClass: PBS_PlainArgumentRecorderPlugin::class,
        targetClass: PBS_Shouter::class,
        beforeMethods: ['shout' => ['pluginMethod' => 'shout', 'sortOrder' => 20]],
    ));

    $resolved = makePbsInterceptor($registry)->createProxy(
        PBS_Shouter::class,
        PBS_Shouter::class,
        new PBS_Shouter(),
    );
    $result = $resolved->shout('quiet');

    expect($result)->toBe('QUIET!')
        ->and(PBS_PlainArgumentRecorderPlugin::$received)->toBe('QUIET');
});

it('short-circuits from a before plugin that takes the subject', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PBS_ShortCircuitPlugin::class,
        targetClass: PBS_GreeterInterface::class,
        beforeMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10, 'subject' => true]],
    ));
    $registry->register(new PluginDefinition(
        pluginClass: PBS_ShortCircuitAfterRecorder::class,
        targetClass: PBS_GreeterInterface::class,
        afterMethods: ['greet' => ['pluginMethod' => 'greet', 'sortOrder' => 10]],
    ));

    $resolved = makePbsInterceptor($registry)->createProxy(
        PBS_GreeterInterface::class,
        PBS_Greeter::class,
        new PBS_Greeter(),
    );
    $result = $resolved->greet('Ada');

    expect($result)->toBe('short-circuited for Ada')
        ->and(PBS_ShortCircuitAfterRecorder::$ran)->toBeFalse();
});
