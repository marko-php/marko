<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Mercure\Exceptions;

use Marko\Broadcasting\Exceptions\BroadcastException;

class MercureException extends BroadcastException
{
    public static function emptySigningKey(): self
    {
        return new self(
            message: 'Mercure JWT signing key is empty.',
            context: 'While signing a Mercure JWT',
            suggestion: 'Set MERCURE_PUBLISHER_JWT_KEY / MERCURE_SUBSCRIBER_JWT_KEY (the same keys your hub is configured with) in your environment.',
        );
    }

    public static function missingPublisherCredentials(): self
    {
        return new self(
            message: 'No Mercure publisher credentials are configured.',
            context: "Both 'broadcasting-mercure.publisher_jwt' and 'broadcasting-mercure.publisher_jwt_key' are empty",
            suggestion: 'Set MERCURE_PUBLISHER_JWT_KEY to the hub\'s publisher key, or MERCURE_PUBLISHER_JWT to a pre-generated publisher token.',
        );
    }

    public static function missingSubscriberKey(): self
    {
        return new self(
            message: 'No Mercure subscriber JWT key is configured.',
            context: "'broadcasting-mercure.subscriber_jwt_key' is empty while issuing a subscriber token",
            suggestion: 'Set MERCURE_SUBSCRIBER_JWT_KEY to the hub\'s subscriber key.',
        );
    }

    public static function unsafeTopic(
        string $topic,
    ): self {
        $printable = addcslashes($topic, "\0..\37\177");

        return new self(
            message: "'$printable' is not a safe Mercure topic.",
            context: 'Mercure reads { } as a URI template and , or * as selector syntax, so this topic could grant access to other topics',
            suggestion: "Remove { } * , whitespace and control characters from the channel name and from 'broadcasting-mercure.topic_prefix'.",
        );
    }
}
