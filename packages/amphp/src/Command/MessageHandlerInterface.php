<?php

declare(strict_types=1);

namespace Marko\Amphp\Command;

use Marko\PubSub\Message;

interface MessageHandlerInterface
{
    public function handle(Message $message): void;
}
