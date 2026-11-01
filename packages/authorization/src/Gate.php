<?php

declare(strict_types=1);

namespace Marko\Authorization;

use Marko\Authentication\Contracts\AbilityScopedGuardInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Contracts\PolicyBeforeInterface;
use Marko\Authorization\Event\AbilityCheckedEvent;
use Marko\Authorization\Event\AbilityDecisionSource;
use Marko\Authorization\Exceptions\AuthorizationException;
use Marko\Authorization\Exceptions\PolicyException;
use Marko\Core\Event\EventDispatcherInterface;

/**
 * Decides abilities in a fixed order:
 *
 * 1. When the guard is an AbilityScopedGuardInterface (the token guard), an
 *    authenticated user's credential must grant the ability: a token scoped
 *    to ['posts:read'] is denied 'posts:delete' whatever else would allow it.
 *    Guests are not affected, so closures and policies that allow guests keep
 *    working.
 * 2. #[GateHook] classes, highest priority first; the first non-null result wins.
 * 3. Closures registered with define().
 * 4. The registered policy: its PolicyBeforeInterface::before() when it has
 *    one, then the ability method.
 * 5. Anything undefined is denied.
 *
 * Every decision is announced with an AbilityCheckedEvent.
 */
class Gate implements GateInterface
{
    /** @var array<string, callable> */
    private array $abilities = [];

    public function __construct(
        private readonly GuardInterface $guard,
        private readonly PolicyRegistry $policyRegistry,
        private readonly GateHookRegistry $gateHookRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function define(
        string $ability,
        callable $callback,
    ): void {
        $this->abilities[$ability] = $callback;
    }

    /**
     * @throws PolicyException
     */
    public function allows(
        string $ability,
        mixed ...$arguments,
    ): bool {
        $user = $this->resolveUser();

        // A scoped credential (an API token) can only narrow the user's authority
        if ($user !== null && $this->guard instanceof AbilityScopedGuardInterface
            && !$this->guard->hasAbility($ability)) {
            return $this->decide($user, $ability, $arguments, false, AbilityDecisionSource::TokenScope);
        }

        foreach ($this->gateHookRegistry->hooks() as $hook) {
            $result = $hook->before($user, $ability, $arguments);

            if ($result !== null) {
                return $this->decide(
                    $user,
                    $ability,
                    $arguments,
                    $result,
                    AbilityDecisionSource::GateHook,
                    $hook::class,
                );
            }
        }

        if (isset($this->abilities[$ability])) {
            $result = (bool) ($this->abilities[$ability])($user, ...$arguments);

            return $this->decide($user, $ability, $arguments, $result, AbilityDecisionSource::Closure);
        }

        $policyClass = $this->resolvePolicyClass($arguments);

        if ($policyClass !== null) {
            return $this->callPolicy($policyClass, $ability, $user, $arguments);
        }

        return $this->decide($user, $ability, $arguments, false, AbilityDecisionSource::DefaultDeny);
    }

    /**
     * @throws PolicyException
     */
    public function denies(
        string $ability,
        mixed ...$arguments,
    ): bool {
        return !$this->allows($ability, ...$arguments);
    }

    /**
     * @throws AuthorizationException|PolicyException
     */
    public function authorize(
        string $ability,
        mixed ...$arguments,
    ): bool {
        if ($this->allows($ability, ...$arguments)) {
            return true;
        }

        throw AuthorizationException::forbidden(
            ability: $ability,
            resource: $this->resolveResourceName($arguments),
        );
    }

    /**
     * @throws PolicyException
     */
    public function policy(
        string $entityClass,
        string $policyClass,
    ): void {
        $this->policyRegistry->register($entityClass, $policyClass);
    }

    private function resolveUser(): ?AuthorizableInterface
    {
        $user = $this->guard->user();

        if ($user instanceof AuthorizableInterface) {
            return $user;
        }

        return null;
    }

    /**
     * The policy registered for the first argument: an entity object or a class string.
     *
     * @param array<mixed> $arguments
     */
    private function resolvePolicyClass(
        array $arguments,
    ): ?string {
        $subject = $arguments[0] ?? null;

        if (is_object($subject)) {
            return $this->policyRegistry->resolve($subject::class);
        }

        if (is_string($subject) && class_exists($subject)) {
            return $this->policyRegistry->resolve($subject);
        }

        return null;
    }

    /**
     * @param array<mixed> $arguments
     * @throws PolicyException
     */
    private function callPolicy(
        string $policyClass,
        string $ability,
        ?AuthorizableInterface $user,
        array $arguments,
    ): bool {
        // Checked before the policy-level before(), so it never hides a missing method
        if (!$this->policyRegistry->hasAbility($policyClass, $ability)) {
            throw PolicyException::missingMethod(
                policyClass: $policyClass,
                ability: $ability,
            );
        }

        $policy = new $policyClass();

        if ($policy instanceof PolicyBeforeInterface) {
            $result = $policy->before($user, $ability, $arguments);

            if ($result !== null) {
                return $this->decide(
                    $user,
                    $ability,
                    $arguments,
                    $result,
                    AbilityDecisionSource::PolicyBefore,
                    $policyClass,
                );
            }
        }

        $result = (bool) $policy->{$ability}($user, ...$arguments);

        return $this->decide($user, $ability, $arguments, $result, AbilityDecisionSource::Policy, $policyClass);
    }

    /**
     * Announce the decision and return it.
     *
     * @param array<mixed> $arguments
     * @param class-string|null $decidedBy
     */
    private function decide(
        ?AuthorizableInterface $user,
        string $ability,
        array $arguments,
        bool $result,
        AbilityDecisionSource $source,
        ?string $decidedBy = null,
    ): bool {
        $this->eventDispatcher->dispatch(new AbilityCheckedEvent(
            user: $user,
            ability: $ability,
            arguments: $arguments,
            result: $result,
            source: $source,
            decidedBy: $decidedBy,
        ));

        return $result;
    }

    /**
     * @param array<mixed> $arguments
     */
    private function resolveResourceName(
        array $arguments,
    ): string {
        if ($arguments !== [] && is_object($arguments[0])) {
            return $arguments[0]::class;
        }

        if ($arguments !== [] && is_string($arguments[0])) {
            return $arguments[0];
        }

        return 'unknown';
    }
}
