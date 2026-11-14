<?php

declare(strict_types=1);

use Marko\Broadcasting\ChannelDefinition;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Discovery\BroadcastChannelDiscovery;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Marko\Broadcasting\PresenceMember;
use Marko\Broadcasting\Tests\Fixtures\PresenceModule\RoomPresenceAuthorizer;
use Marko\Broadcasting\Tests\Fixtures\ValidModule\AdminChannelAuthorizer;
use Marko\Broadcasting\Tests\Fixtures\ValidModule\ShowChannelAuthorizer;
use Marko\Core\Container\Container;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Testing\Fake\FakeAuthenticatable;

function broadcastChannelDiscovery(
    string $fixture,
): BroadcastChannelDiscovery {
    return new BroadcastChannelDiscovery(
        moduleRepository: new ModuleRepository([
            new ModuleManifest(
                name: "test/$fixture",
                version: '1.0.0',
                path: dirname(__DIR__) . "/Fixtures/$fixture",
            ),
        ]),
        classFileParser: new ClassFileParser(),
    );
}

function channelRegistry(
    string $fixture = 'ValidModule',
): ChannelRegistry {
    return new ChannelRegistry(
        broadcastChannelDiscovery: broadcastChannelDiscovery($fixture),
        container: new Container(),
    );
}

describe('BroadcastChannelDiscovery', function (): void {
    it('discovers BroadcastChannel classes in module src directories', function (): void {
        $definitions = broadcastChannelDiscovery('ValidModule')->discover();

        $byPattern = [];
        foreach ($definitions as $definition) {
            $byPattern[$definition->pattern] = $definition->authorizerClass;
        }
        ksort($byPattern);

        expect($definitions)->each->toBeInstanceOf(ChannelDefinition::class)
            ->and($byPattern)->toBe([
                'admin' => AdminChannelAuthorizer::class,
                'shows.{showId}.seats.{seatId}' => ShowChannelAuthorizer::class,
            ]);
    });

    it('discovers presence channel authorizers', function (): void {
        $definitions = broadcastChannelDiscovery('PresenceModule')->discover();

        expect($definitions)->toHaveCount(1)
            ->and($definitions[0]->pattern)->toBe('rooms.{roomId}')
            ->and($definitions[0]->authorizerClass)->toBe(RoomPresenceAuthorizer::class);
    });

    it('throws when a BroadcastChannel class does not implement ChannelAuthorizerInterface', function (): void {
        expect(fn () => broadcastChannelDiscovery('InvalidModule')->discover())
            ->toThrow(ChannelAuthorizationException::class, 'must implement ChannelAuthorizerInterface');
    });

    it('throws when two authorizers register the same pattern', function (): void {
        expect(fn () => broadcastChannelDiscovery('DuplicateModule')->discover())
            ->toThrow(ChannelAuthorizationException::class, "Channel pattern 'orders.{orderId}' is registered twice");
    });
});

describe('ChannelRegistry', function (): void {
    it('allows a channel when the authorizer returns true', function (): void {
        expect(channelRegistry()->authorize('admin', new FakeAuthenticatable(id: 1)))->toBeTrue();
    });

    it('denies a channel when the authorizer returns false', function (): void {
        $registry = channelRegistry();

        expect($registry->authorize('admin', new FakeAuthenticatable(id: 2)))->toBeFalse()
            ->and($registry->authorize('admin', null))->toBeFalse();
    });

    it('passes named pattern parameters to the authorizer', function (): void {
        $registry = channelRegistry();
        $user = new FakeAuthenticatable();

        expect($registry->authorize('shows.42.seats.A1', $user))->toBeTrue()
            ->and($registry->authorize('shows.43.seats.A1', $user))->toBeFalse();
    });

    it('does not let a parameter span multiple dot-separated segments', function (): void {
        expect(fn () => channelRegistry()->authorize('shows.4.2.seats.A1', new FakeAuthenticatable()))
            ->toThrow(ChannelAuthorizationException::class);
    });

    it('does not let a parameter capture characters outside the safe charset', function (string $channelName): void {
        expect(fn () => channelRegistry()->authorize($channelName, new FakeAuthenticatable()))
            ->toThrow(ChannelAuthorizationException::class);
    })->with([
        'URI template' => ['shows.42{x}.seats.A1'],
        'comma list' => ['shows.42,43.seats.A1'],
        'wildcard' => ['shows.*.seats.A1'],
        'space' => ['shows.42 .seats.A1'],
        'trailing newline' => ["shows.42.seats.A1\n"],
    ]);

    it('passes parameters made of the safe placeholder charset to the authorizer', function (): void {
        expect(channelRegistry()->authorize('shows.42.seats.A_1-b=c@d', new FakeAuthenticatable()))->toBeFalse()
            ->and(channelRegistry()->authorize('shows.42.seats.A1', new FakeAuthenticatable()))->toBeTrue();
    });

    it('throws ChannelAuthorizationException for an unknown private channel', function (): void {
        try {
            channelRegistry()->authorize('invoices.9', new FakeAuthenticatable());
            $this->fail('Expected ChannelAuthorizationException');
        } catch (ChannelAuthorizationException $exception) {
            expect($exception->getMessage())->toContain("No authorizer is registered for private channel 'invoices.9'")
                ->and($exception->getSuggestion())->toContain('#[BroadcastChannel(');
        }
    });

    it('lists the registered channel patterns', function (): void {
        $patterns = channelRegistry()->patterns();
        sort($patterns);

        expect($patterns)->toBe(['admin', 'shows.{showId}.seats.{seatId}']);
    });

    it('returns the member when the presence authorizer allows the user', function (): void {
        $member = channelRegistry('PresenceModule')->authorizePresence('rooms.7', new FakeAuthenticatable(id: 5));

        expect($member)->toBeInstanceOf(PresenceMember::class)
            ->and($member->id)->toBe(5);
    });

    it('returns null when the presence authorizer denies the user', function (): void {
        expect(channelRegistry('PresenceModule')->authorizePresence('rooms.7', null))->toBeNull();
    });

    it('passes named pattern parameters to the presence authorizer', function (): void {
        $member = channelRegistry('PresenceModule')->authorizePresence('rooms.lobby', new FakeAuthenticatable());

        expect($member->info)->toBe(['room' => 'lobby']);
    });

    it('throws a clear exception when a private-only authorizer matches a presence channel', function (): void {
        try {
            channelRegistry()->authorizePresence('admin', new FakeAuthenticatable(id: 1));
            $this->fail('Expected ChannelAuthorizationException');
        } catch (ChannelAuthorizationException $exception) {
            expect($exception->getMessage())->toContain(AdminChannelAuthorizer::class)
                ->and($exception->getSuggestion())->toContain('PresenceChannelAuthorizerInterface');
        }
    });

    it('throws a clear exception when a presence authorizer matches a private channel', function (): void {
        try {
            channelRegistry('PresenceModule')->authorize('rooms.7', new FakeAuthenticatable());
            $this->fail('Expected ChannelAuthorizationException');
        } catch (ChannelAuthorizationException $exception) {
            expect($exception->getMessage())->toContain(RoomPresenceAuthorizer::class)
                ->toContain("private channel 'rooms.7'")
                ->and($exception->getSuggestion())->toContain('ChannelAuthorizerInterface');
        }
    });

    it('throws ChannelAuthorizationException for an unknown presence channel', function (): void {
        try {
            channelRegistry('PresenceModule')->authorizePresence('lobbies.9', new FakeAuthenticatable());
            $this->fail('Expected ChannelAuthorizationException');
        } catch (ChannelAuthorizationException $exception) {
            expect($exception->getMessage())->toContain("No authorizer is registered for presence channel 'lobbies.9'")
                ->and($exception->getSuggestion())->toContain('PresenceChannelAuthorizerInterface');
        }
    });
});
