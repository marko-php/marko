<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Subscriber;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Broadcasting\Amphp\AmphpBroadcastingConfig;
use Marko\Broadcasting\Amphp\Auth\AmphpSignature;
use Marko\Broadcasting\Amphp\Driver\AmphpBroadcaster;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Channel;
use Marko\Broadcasting\ChannelRegistry;
use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Exceptions\ChannelAuthorizationException;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

/**
 * Issues subscriber tokens for the `broadcasting:serve` stream. Private channels are only
 * included after the ChannelRegistry authorizes them for the given user; public channels need
 * no token.
 */
readonly class AmphpSubscriberToken
{
    public function __construct(
        private AmphpSignature $amphpSignature,
        private AmphpBroadcastingConfig $amphpBroadcastingConfig,
        private ChannelRegistry $channelRegistry,
        private ClockInterface $clock,
    ) {}

    /**
     * Sign a token listing the private channels the user is authorized for.
     *
     * @param list<string|Channel> $channels Plain strings are public channels
     * @throws AmphpBroadcastException|BroadcastException|ChannelAuthorizationException|ReflectionException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function for(
        array $channels,
        ?AuthenticatableInterface $user,
    ): string {
        if ($this->amphpBroadcastingConfig->appKey === '') {
            throw AmphpBroadcastException::missingAppKey();
        }

        $authorized = [];

        foreach ($channels as $channel) {
            $channel = $this->normalize($channel);

            if (
                $channel->isPrivate()
                && $this->channelRegistry->authorize($channel->name, $user)
            ) {
                $authorized[] = $this->streamChannel($channel);
            }
        }

        return $this->amphpSignature->sign(
            $user?->getAuthIdentifier(),
            $authorized,
            $this->clock->now()->getTimestamp() + $this->amphpBroadcastingConfig->tokenTtl,
        );
    }

    /**
     * The URL for the browser's EventSource. A token is added when any private channel is requested.
     *
     * @param list<string|Channel> $channels
     * @throws AmphpBroadcastException|BroadcastException|ChannelAuthorizationException|ReflectionException|ContainerExceptionInterface|NotFoundExceptionInterface
     */
    public function streamUrl(
        array $channels,
        ?AuthenticatableInterface $user,
    ): string {
        $normalized = array_map(
            $this->normalize(...),
            $channels,
        );
        $query = ['channels' => implode(',', array_map($this->streamChannel(...), $normalized))];

        if (array_any($normalized, fn (Channel $channel): bool => $channel->isPrivate())) {
            $query['token'] = $this->for($channels, $user);
        }

        return rtrim($this->amphpBroadcastingConfig->publicUrl, '/')
            . $this->amphpBroadcastingConfig->path
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Rejects presence channels and any name outside the stream's channel charset. Names are
     * checked before authorization, so a name with a comma (the stream's channel separator) can
     * never be authorized and signed.
     *
     * @throws BroadcastException
     */
    private function normalize(
        string|Channel $channel,
    ): Channel {
        $channel = Channel::from($channel);

        if ($channel->isPresence()) {
            throw BroadcastException::presenceChannelsUnsupported('Amphp', $channel->name);
        }

        if (preg_match(AmphpBroadcaster::CHANNEL_NAME_PATTERN, $channel->name) !== 1) {
            throw BroadcastException::invalidChannelName(
                'Amphp',
                $channel->name,
                AmphpBroadcaster::CHANNEL_NAME_ALLOWED,
            );
        }

        return $channel;
    }

    private function streamChannel(
        Channel $channel,
    ): string {
        return $channel->isPrivate() ? AmphpBroadcaster::PRIVATE_PREFIX . $channel->name : $channel->name;
    }
}
