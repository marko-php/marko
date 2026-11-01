<?php

declare(strict_types=1);

namespace Marko\Authorization\Tests\Unit;

use Marko\Authentication\Contracts\AbilityScopedGuardInterface;
use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Contracts\PolicyBeforeInterface;
use Marko\Authorization\Event\AbilityCheckedEvent;
use Marko\Authorization\Event\AbilityDecisionSource;
use Marko\Authorization\Exceptions\PolicyException;
use Marko\Authorization\Gate;
use Marko\Authorization\GateHookRegistry;
use Marko\Authorization\PolicyRegistry;
use Marko\Authorization\Tests\Support\GateHooks;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeGuard;

class HookUser extends FakeAuthenticatable implements AuthorizableInterface
{
    public function __construct(
        public readonly bool $isAdmin = false,
        int $id = 1,
    ) {
        parent::__construct(id: $id);
    }

    public function can(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return false;
    }
}

/**
 * A hook that returns a fixed result and records every call it receives.
 */
class RecordingHook implements GateHookInterface
{
    /** @var array<int, array{user: ?AuthorizableInterface, ability: string, arguments: array<int, mixed>}> */
    public array $calls = [];

    public function __construct(
        private readonly ?bool $result,
    ) {}

    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        $this->calls[] = ['user' => $user, 'ability' => $ability, 'arguments' => $arguments];

        return $this->result;
    }
}

class AllowingHook extends RecordingHook
{
    public function __construct()
    {
        parent::__construct(true);
    }
}

class DenyingHook extends RecordingHook
{
    public function __construct()
    {
        parent::__construct(false);
    }
}

class PassingHook extends RecordingHook
{
    public function __construct()
    {
        parent::__construct(null);
    }
}

class HookedSound
{
    public function __construct(
        public readonly int $ownerId = 1,
    ) {}
}

class HookedSoundPolicy
{
    public function update(
        ?AuthorizableInterface $user,
        HookedSound $sound,
    ): bool {
        return $user !== null && $user->getAuthIdentifier() === $sound->ownerId;
    }
}

class HookedTrack
{
    public function __construct(
        public readonly bool $locked = false,
    ) {}
}

class HookedTrackPolicy implements PolicyBeforeInterface
{
    public static int $updateCalls = 0;

    public function before(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
    ): ?bool {
        if ($user instanceof HookUser && $user->isAdmin) {
            return true;
        }

        return $arguments[0] instanceof HookedTrack && $arguments[0]->locked ? false : null;
    }

    public function update(
        ?AuthorizableInterface $user,
        HookedTrack $track,
    ): bool {
        self::$updateCalls++;

        return $user !== null;
    }
}

class ScopedHookGuard extends FakeGuard implements AbilityScopedGuardInterface
{
    /**
     * @param array<string> $grantedAbilities
     */
    public function __construct(
        private readonly array $grantedAbilities,
    ) {
        parent::__construct(name: 'api', attemptResult: false);
    }

    public function hasAbility(
        string $ability,
    ): bool {
        return in_array($ability, $this->grantedAbilities, true);
    }
}

function hookedGate(
    GateHookRegistry $registry,
    ?AuthorizableInterface $user = null,
    ?FakeGuard $guard = null,
    ?FakeEventDispatcher $events = null,
): Gate {
    $guard ??= new FakeGuard(name: 'hooks', attemptResult: false);
    $guard->setUser($user);

    $policies = new PolicyRegistry();
    $policies->register(HookedSound::class, HookedSoundPolicy::class);
    $policies->register(HookedTrack::class, HookedTrackPolicy::class);

    return new Gate(
        guard: $guard,
        policyRegistry: $policies,
        gateHookRegistry: $registry,
        eventDispatcher: $events ?? new FakeEventDispatcher(),
    );
}

function lastAbilityCheck(
    FakeEventDispatcher $events,
): AbilityCheckedEvent {
    $checks = $events->dispatched(AbilityCheckedEvent::class);
    $last = end($checks);

    expect($last)->toBeInstanceOf(AbilityCheckedEvent::class);

    /** @var AbilityCheckedEvent $last */
    return $last;
}

describe('Gate before hooks', function (): void {
    it('allows when a hook returns true even without a matching policy method', function (): void {
        $gate = hookedGate(GateHooks::registry(new AllowingHook()), new HookUser());

        expect($gate->allows('publish', new HookedSound()))->toBeTrue();
    });

    it('denies when a hook returns false', function (): void {
        // The policy would allow: user 1 owns sound 1
        $gate = hookedGate(GateHooks::registry(new DenyingHook()), new HookUser(id: 1));

        expect($gate->allows('update', new HookedSound(ownerId: 1)))->toBeFalse();
    });

    it('falls through to the policy when a hook returns null', function (): void {
        $hook = new PassingHook();
        $gate = hookedGate(GateHooks::registry($hook), new HookUser(id: 1));

        expect($gate->allows('update', new HookedSound(ownerId: 1)))->toBeTrue()
            ->and($gate->allows('update', new HookedSound(ownerId: 2)))->toBeFalse()
            ->and($hook->calls)->toHaveCount(2);
    });

    it('runs hooks in priority order and stops at the first non-null result', function (): void {
        $first = new PassingHook();
        $second = new DenyingHook();
        $third = new AllowingHook();
        $gate = hookedGate(GateHooks::registry($first, $second, $third), new HookUser());

        expect($gate->allows('update', new HookedSound()))->toBeFalse()
            ->and($first->calls)->toHaveCount(1)
            ->and($second->calls)->toHaveCount(1)
            ->and($third->calls)->toBe([]);
    });

    it('passes the user, ability and arguments to the hook', function (): void {
        $hook = new PassingHook();
        $user = new HookUser();
        $sound = new HookedSound();
        $gate = hookedGate(GateHooks::registry($hook), $user);

        $gate->allows('update', $sound, 'extra');

        expect($hook->calls)->toBe([['user' => $user, 'ability' => 'update', 'arguments' => [$sound, 'extra']]]);
    });

    it('passes null to the hook for a guest', function (): void {
        $hook = new PassingHook();
        $gate = hookedGate(GateHooks::registry($hook));

        $gate->allows('update', new HookedSound());

        expect($hook->calls[0]['user'])->toBeNull();
    });

    it('does not let a hook widen a scoped token', function (): void {
        $hook = new AllowingHook();
        $gate = hookedGate(
            GateHooks::registry($hook),
            new HookUser(isAdmin: true),
            new ScopedHookGuard(['read']),
        );

        expect($gate->allows('read', new HookedSound()))->toBeTrue()
            ->and($gate->allows('delete', new HookedSound()))->toBeFalse()
            ->and($hook->calls)->toHaveCount(1);
    });

    it('applies hooks to define() closures', function (): void {
        $gate = hookedGate(GateHooks::registry(new DenyingHook()), new HookUser());
        $gate->define('view-reports', fn (?AuthorizableInterface $user): bool => true);

        expect($gate->allows('view-reports'))->toBeFalse();
    });

    it('still throws for a missing policy method when every hook returns null', function (): void {
        $gate = hookedGate(GateHooks::registry(new PassingHook(), new PassingHook()), new HookUser());

        expect(fn () => $gate->allows('publish', new HookedSound()))
            ->toThrow(PolicyException::class, "does not have a 'publish' method");
    });
});

describe('policy-level before', function (): void {
    beforeEach(function (): void {
        HookedTrackPolicy::$updateCalls = 0;
    });

    it('allows without calling the ability method when before returns true', function (): void {
        $gate = hookedGate(GateHooks::registry(), new HookUser(isAdmin: true));

        expect($gate->allows('update', new HookedTrack(locked: true)))->toBeTrue()
            ->and(HookedTrackPolicy::$updateCalls)->toBe(0);
    });

    it('denies without calling the ability method when before returns false', function (): void {
        $gate = hookedGate(GateHooks::registry(), new HookUser());

        expect($gate->allows('update', new HookedTrack(locked: true)))->toBeFalse()
            ->and(HookedTrackPolicy::$updateCalls)->toBe(0);
    });

    it('calls the ability method when before returns null', function (): void {
        $gate = hookedGate(GateHooks::registry(), new HookUser());

        expect($gate->allows('update', new HookedTrack()))->toBeTrue()
            ->and(HookedTrackPolicy::$updateCalls)->toBe(1);
    });

    it('runs after the global hooks', function (): void {
        $gate = hookedGate(GateHooks::registry(new DenyingHook()), new HookUser(isAdmin: true));

        expect($gate->allows('update', new HookedTrack()))->toBeFalse();
    });

    it('does not run for an ability the policy does not define', function (): void {
        $gate = hookedGate(GateHooks::registry(), new HookUser(isAdmin: true));

        expect(fn () => $gate->allows('publish', new HookedTrack()))
            ->toThrow(PolicyException::class, "does not have a 'publish' method");
    });
});

describe('AbilityCheckedEvent', function (): void {
    it('reports a deciding hook class', function (): void {
        $events = new FakeEventDispatcher();
        $user = new HookUser();
        $sound = new HookedSound();
        $gate = hookedGate(GateHooks::registry(new PassingHook(), new AllowingHook()), $user, events: $events);

        $gate->allows('update', $sound);
        $event = lastAbilityCheck($events);

        expect($event->user)->toBe($user)
            ->and($event->ability)->toBe('update')
            ->and($event->arguments)->toBe([$sound])
            ->and($event->result)->toBeTrue()
            ->and($event->source)->toBe(AbilityDecisionSource::GateHook)
            ->and($event->decidedBy)->toBe(AllowingHook::class);
    });

    it('reports a closure', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(new PassingHook()), new HookUser(), events: $events);
        $gate->define('view-reports', fn (): bool => false);

        $gate->allows('view-reports');
        $event = lastAbilityCheck($events);

        expect($event->result)->toBeFalse()
            ->and($event->source)->toBe(AbilityDecisionSource::Closure)
            ->and($event->decidedBy)->toBeNull();
    });

    it('reports a policy', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(), new HookUser(id: 1), events: $events);

        $gate->allows('update', new HookedSound(ownerId: 1));
        $event = lastAbilityCheck($events);

        expect($event->result)->toBeTrue()
            ->and($event->source)->toBe(AbilityDecisionSource::Policy)
            ->and($event->decidedBy)->toBe(HookedSoundPolicy::class);
    });

    it('reports a policy-level before', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(), new HookUser(isAdmin: true), events: $events);

        $gate->allows('update', new HookedTrack());
        $event = lastAbilityCheck($events);

        expect($event->result)->toBeTrue()
            ->and($event->source)->toBe(AbilityDecisionSource::PolicyBefore)
            ->and($event->decidedBy)->toBe(HookedTrackPolicy::class);
    });

    it('reports the default deny', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(), new HookUser(), events: $events);

        $gate->allows('undefined-ability');
        $event = lastAbilityCheck($events);

        expect($event->result)->toBeFalse()
            ->and($event->source)->toBe(AbilityDecisionSource::DefaultDeny)
            ->and($event->decidedBy)->toBeNull();
    });

    it('reports a scoped token denial', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(
            GateHooks::registry(new AllowingHook()),
            new HookUser(),
            new ScopedHookGuard(['read']),
            $events,
        );

        $gate->allows('delete', new HookedSound());
        $event = lastAbilityCheck($events);

        expect($event->result)->toBeFalse()
            ->and($event->source)->toBe(AbilityDecisionSource::TokenScope)
            ->and($event->decidedBy)->toBeNull();
    });

    it('dispatches one event per check, including from authorize() and denies()', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(new AllowingHook()), new HookUser(), events: $events);

        $gate->allows('a');
        $gate->denies('b');
        $gate->authorize('c');

        expect(array_map(
            fn (AbilityCheckedEvent $event): string => $event->ability,
            $events->dispatched(AbilityCheckedEvent::class),
        ))->toBe(['a', 'b', 'c']);
    });

    it('dispatches nothing when the check throws a PolicyException', function (): void {
        $events = new FakeEventDispatcher();
        $gate = hookedGate(GateHooks::registry(), new HookUser(), events: $events);

        expect(fn () => $gate->allows('publish', new HookedSound()))->toThrow(PolicyException::class)
            ->and($events->dispatched(AbilityCheckedEvent::class))->toBe([]);
    });
});
