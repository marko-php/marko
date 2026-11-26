<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Subject;
use Marko\Core\Container\Container;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;

// All fixture classes are prefixed with PAS_ to avoid conflicts.

interface PAS_ConnectionInterface
{
    /**
     * @return array<int, array<string, int>>
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array;
}

class PAS_Connection implements PAS_ConnectionInterface
{
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        return [['id' => count($bindings)]];
    }
}

class PAS_QueryRecorderPlugin
{
    public static ?PAS_ConnectionInterface $subject = null;

    public static ?array $result = null;

    public static ?string $sql = null;

    public static ?array $bindings = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterQuery(
        #[Subject]
        PAS_ConnectionInterface $connection,
        array $result,
        string $sql,
        array $bindings = [],
    ): array {
        self::$subject = $connection;
        self::$result = $result;
        self::$sql = $sql;
        self::$bindings = $bindings;

        return $result;
    }
}

#[Plugin(target: PAS_ConnectionInterface::class)]
class PAS_DiscoveredPlugin
{
    /** @var list<PAS_ConnectionInterface> */
    public static array $subjects = [];

    /** @noinspection PhpUnused - Invoked via reflection */
    #[Before(method: 'query')]
    public function beforeQuery(
        #[Subject]
        PAS_ConnectionInterface $connection,
        string $sql,
        array $bindings = [],
    ): null {
        self::$subjects[] = $connection;

        return null;
    }

    /** @noinspection PhpUnused - Invoked via reflection */
    #[After(method: 'query')]
    public function afterQuery(
        #[Subject]
        PAS_ConnectionInterface $connection,
        array $result,
        string $sql,
        array $bindings = [],
    ): array {
        self::$subjects[] = $connection;

        return $result;
    }
}

class PAS_Counter
{
    public int $count = 0;

    public function bump(): int
    {
        $this->count++;

        return $this->count;
    }
}

class PAS_CountReaderPlugin
{
    public static ?int $countSeen = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterBump(
        mixed $subject = null,
        mixed $result = null,
    ): int {
        self::$countSeen = is_object($subject) ? $subject->count : null;

        return is_int($result) ? $result : -1;
    }
}

class PAS_Joiner
{
    public function join(
        string $word,
    ): string {
        return 'joined ' . $word;
    }
}

class PAS_FirstSubjectPlugin
{
    public static mixed $subject = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterJoin(
        mixed $subject = null,
        mixed $result = null,
    ): string {
        self::$subject = $subject;

        return $result . '+first';
    }
}

class PAS_MiddlePlainPlugin
{
    public static ?string $received = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterJoin(
        string $result,
    ): string {
        self::$received = $result;

        return $result . '+middle';
    }
}

class PAS_LastSubjectPlugin
{
    public static mixed $received = null;

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterJoin(
        mixed $subject = null,
        mixed $result = null,
    ): string {
        self::$received = $result;

        return $result . '+last';
    }
}

class PAS_Channel
{
    public function write(
        string $message,
    ): string {
        return 'wrote ' . $message;
    }
}

class PAS_ChannelPlugin
{
    /** @var array<int, mixed> */
    public static array $subjects = [];

    /** @noinspection PhpUnused - Invoked via reflection */
    public function afterWrite(
        mixed $subject = null,
        mixed $result = null,
    ): mixed {
        self::$subjects[] = $subject;

        return $result;
    }
}

function makePasInterceptor(
    PluginRegistry $registry,
): PluginInterceptor {
    return new PluginInterceptor(new Container(), $registry, new InterceptorClassGenerator());
}

beforeEach(function (): void {
    PAS_QueryRecorderPlugin::$subject = null;
    PAS_QueryRecorderPlugin::$result = null;
    PAS_QueryRecorderPlugin::$sql = null;
    PAS_QueryRecorderPlugin::$bindings = null;
    PAS_CountReaderPlugin::$countSeen = null;
    PAS_FirstSubjectPlugin::$subject = null;
    PAS_MiddlePlainPlugin::$received = null;
    PAS_LastSubjectPlugin::$received = null;
    PAS_ChannelPlugin::$subjects = [];
    PAS_DiscoveredPlugin::$subjects = [];
});

it('passes the subject first and the result second to an after plugin on an interface target', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PAS_QueryRecorderPlugin::class,
        targetClass: PAS_ConnectionInterface::class,
        afterMethods: ['query' => ['pluginMethod' => 'afterQuery', 'sortOrder' => 10, 'subject' => true]],
    ));

    $resolved = makePasInterceptor($registry)->createProxy(
        PAS_ConnectionInterface::class,
        PAS_Connection::class,
        new PAS_Connection(),
    );
    $result = $resolved->query('select 1', ['a']);

    expect(PAS_QueryRecorderPlugin::$subject)->toBe($resolved)
        ->and(PAS_QueryRecorderPlugin::$result)->toBe([['id' => 1]])
        ->and(PAS_QueryRecorderPlugin::$sql)->toBe('select 1')
        ->and(PAS_QueryRecorderPlugin::$bindings)->toBe(['a'])
        ->and($result)->toBe([['id' => 1]]);
});

it(
    'gives an after plugin on a concrete target a subject that shows state the target method just changed',
    function (): void {
        $registry = new PluginRegistry();
        $registry->register(new PluginDefinition(
            pluginClass: PAS_CountReaderPlugin::class,
            targetClass: PAS_Counter::class,
            afterMethods: ['bump' => ['pluginMethod' => 'afterBump', 'sortOrder' => 10, 'subject' => true]],
        ));

        $resolved = makePasInterceptor($registry)->createProxy(
            PAS_Counter::class,
            PAS_Counter::class,
            new PAS_Counter(),
        );
        $resolved->bump();
        $result = $resolved->bump();

        expect(PAS_CountReaderPlugin::$countSeen)->toBe(2)
            ->and($result)->toBe(2);
    },
);

it('chains results between after plugins that take the subject and ones that do not', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PAS_FirstSubjectPlugin::class,
        targetClass: PAS_Joiner::class,
        afterMethods: ['join' => ['pluginMethod' => 'afterJoin', 'sortOrder' => 10, 'subject' => true]],
    ));
    $registry->register(new PluginDefinition(
        pluginClass: PAS_MiddlePlainPlugin::class,
        targetClass: PAS_Joiner::class,
        afterMethods: ['join' => ['pluginMethod' => 'afterJoin', 'sortOrder' => 20]],
    ));
    $registry->register(new PluginDefinition(
        pluginClass: PAS_LastSubjectPlugin::class,
        targetClass: PAS_Joiner::class,
        afterMethods: ['join' => ['pluginMethod' => 'afterJoin', 'sortOrder' => 30, 'subject' => true]],
    ));

    $resolved = makePasInterceptor($registry)->createProxy(
        PAS_Joiner::class,
        PAS_Joiner::class,
        new PAS_Joiner(),
    );
    $result = $resolved->join('words');

    expect($result)->toBe('joined words+first+middle+last')
        ->and(PAS_FirstSubjectPlugin::$subject)->toBe($resolved)
        ->and(PAS_MiddlePlainPlugin::$received)->toBe('joined words+first')
        ->and(PAS_LastSubjectPlugin::$received)->toBe('joined words+first+middle');
});

it('tells two non-shared instances of the same target apart in one plugin', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDefinition(
        pluginClass: PAS_ChannelPlugin::class,
        targetClass: PAS_Channel::class,
        afterMethods: ['write' => ['pluginMethod' => 'afterWrite', 'sortOrder' => 10, 'subject' => true]],
    ));
    $container = new Container();
    $container->setPluginInterceptor(new PluginInterceptor($container, $registry, new InterceptorClassGenerator()));

    $first = $container->get(PAS_Channel::class);
    $second = $container->get(PAS_Channel::class);
    $first->write('one');
    $second->write('two');
    $first->write('three');

    expect($first)->not->toBe($second)
        ->and(PAS_ChannelPlugin::$subjects)->toHaveCount(3)
        ->and(PAS_ChannelPlugin::$subjects[0])->toBe($first)
        ->and(PAS_ChannelPlugin::$subjects[1])->toBe($second)
        ->and(PAS_ChannelPlugin::$subjects[2])->toBe($first);
});

it('passes the subject to before and after plugins discovered from their attributes', function (): void {
    $registry = new PluginRegistry();
    $registry->register(new PluginDiscovery()->parsePluginClass(PAS_DiscoveredPlugin::class));

    $resolved = makePasInterceptor($registry)->createProxy(
        PAS_ConnectionInterface::class,
        PAS_Connection::class,
        new PAS_Connection(),
    );
    $result = $resolved->query('select 1', ['a']);

    expect(PAS_DiscoveredPlugin::$subjects)->toBe([$resolved, $resolved])
        ->and($result)->toBe([['id' => 1]]);
});
