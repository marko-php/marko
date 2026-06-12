<?php

declare(strict_types=1);

namespace Marko\Amphp\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class AmphpException extends MarkoException
{
    public static function noChannelsConfigured(): self
    {
        return new self(
            message: 'No pub/sub channels are configured for the listener',
            context: 'While starting pubsub:listen',
            suggestion: "Add channel names to the 'amphp.channels' config key (e.g. AMPHP_CHANNELS=orders,notifications)",
        );
    }
}
