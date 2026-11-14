<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Exceptions;

class ChannelAuthorizationException extends BroadcastException
{
    public static function unknownChannel(
        string $channelName,
    ): self {
        return new self(
            message: "No authorizer is registered for private channel '$channelName'.",
            context: "While authorizing a subscription to private channel '$channelName'",
            suggestion: "Create a class implementing ChannelAuthorizerInterface and mark it with #[BroadcastChannel('...')] "
                . 'using a pattern that matches this channel (placeholders like {id} match one dot-separated segment). '
                . 'Private channels are never allowed by default.',
        );
    }

    public static function unknownPresenceChannel(
        string $channelName,
    ): self {
        return new self(
            message: "No authorizer is registered for presence channel '$channelName'.",
            context: "While authorizing a subscription to presence channel '$channelName'",
            suggestion: "Create a class implementing PresenceChannelAuthorizerInterface and mark it with #[BroadcastChannel('...')] "
                . 'using a pattern that matches this channel (placeholders like {id} match one dot-separated segment). '
                . 'Presence channels are never allowed by default.',
        );
    }

    public static function presenceAuthorizerRequired(
        string $channelName,
        string $authorizerClass,
    ): self {
        return new self(
            message: "Authorizer '$authorizerClass' matches presence channel '$channelName' but does not implement PresenceChannelAuthorizerInterface.",
            context: "While authorizing a subscription to presence channel '$channelName'",
            suggestion: "Implement PresenceChannelAuthorizerInterface on $authorizerClass so authorize() returns a PresenceMember (or null to deny).",
        );
    }

    public static function privateAuthorizerRequired(
        string $channelName,
        string $authorizerClass,
    ): self {
        return new self(
            message: "Authorizer '$authorizerClass' matches private channel '$channelName' but implements PresenceChannelAuthorizerInterface.",
            context: "While authorizing a subscription to private channel '$channelName'",
            suggestion: "Private channels need a ChannelAuthorizerInterface implementation that returns a bool. $authorizerClass serves presence channels only.",
        );
    }

    public static function notAnAuthorizer(
        string $className,
    ): self {
        return new self(
            message: "Class '$className' has #[BroadcastChannel] but must implement ChannelAuthorizerInterface or PresenceChannelAuthorizerInterface.",
            context: 'While discovering #[BroadcastChannel] authorizers',
            suggestion: "Add 'implements ChannelAuthorizerInterface' (private channels) or 'implements PresenceChannelAuthorizerInterface' (presence channels) to $className and implement authorize().",
        );
    }

    /**
     * @param list<string> $classNames
     */
    public static function duplicatePattern(
        string $pattern,
        array $classNames,
    ): self {
        $classList = implode(', ', $classNames);

        return new self(
            message: "Channel pattern '$pattern' is registered twice: $classList",
            context: 'While discovering #[BroadcastChannel] authorizers',
            suggestion: 'Keep a single authorizer per pattern. To replace a vendor authorizer, use #[Preference] on a subclass instead of registering the pattern again.',
        );
    }
}
